<?php

namespace Tests\Feature;

use App\Enums\MatterDifficulty;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Filament\Actions\Request\ApproveRequestAction;
use App\Filament\Resources\MatterRequests\Pages\ViewMatterRequest;
use App\Models\Matter;
use App\Models\MatterRequest;
use App\Models\User;
use App\Services\Requests\ChangeDifficultyRequestService;
use App\Services\Requests\ChangeDistributedAtRequestService;
use App\Services\Requests\ConfirmReportRequestService;
use App\Services\Requests\RequestServiceFactory;
use App\Services\Requests\ReviewReportRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MatterRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // This system's request-type actions gate almost everything behind
        // Spatie ability checks; bypass them here the same way TypeResourceTest
        // does, so tests exercise the request-type logic itself, not permissions.
        Gate::before(fn () => true);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    private function makeMatter(array $overrides = []): Matter
    {
        return Matter::create(array_merge([
            'number' => '1',
            'year' => '2026',
            'difficulty' => MatterDifficulty::EASY,
        ], $overrides));
    }

    public function test_class_for_maps_every_request_type_to_its_service(): void
    {
        $this->assertSame(ChangeDifficultyRequestService::class, RequestServiceFactory::classFor(RequestType::CHANGE_DIFFICULTY));
        $this->assertSame(ReviewReportRequestService::class, RequestServiceFactory::classFor(RequestType::REVIEW_REPORT));
        $this->assertSame(ChangeDistributedAtRequestService::class, RequestServiceFactory::classFor(RequestType::CHANGE_DISTRIBUTED_DATE));
        $this->assertSame(ConfirmReportRequestService::class, RequestServiceFactory::classFor(RequestType::CONFIRM_REPORT));
    }

    public function test_change_difficulty_prepares_extra_and_approving_updates_the_matter(): void
    {
        $matter = $this->makeMatter(['difficulty' => MatterDifficulty::EASY]);
        $user = User::factory()->create();

        $prepared = ChangeDifficultyRequestService::prepareForCreation([
            'comment' => 'Please bump difficulty',
            'new_difficulty' => MatterDifficulty::HARD,
        ], $matter);

        $this->assertSame(['new_difficulty' => 'hard'], $prepared['extra']);
        $this->assertStringContainsString(MatterDifficulty::HARD->getLabel(), $prepared['comment']);

        $request = MatterRequest::create([
            'matter_id' => $matter->id,
            'request_by' => $user->id,
            'type' => RequestType::CHANGE_DIFFICULTY,
            'status' => 'pending',
            'comment' => $prepared['comment'],
            'extra' => $prepared['extra'],
        ]);

        RequestServiceFactory::make($request)->approve(['approved_comment' => 'ok', 'attachments' => []]);

        $this->assertEquals(RequestStatus::APPROVED, $request->fresh()->status);
        $this->assertEquals(MatterDifficulty::HARD, $matter->fresh()->difficulty);
    }

    public function test_change_difficulty_reject_leaves_the_matter_untouched(): void
    {
        $matter = $this->makeMatter(['difficulty' => MatterDifficulty::EASY]);
        $user = User::factory()->create();

        $request = MatterRequest::create([
            'matter_id' => $matter->id,
            'request_by' => $user->id,
            'type' => RequestType::CHANGE_DIFFICULTY,
            'status' => 'pending',
            'comment' => 'x',
            'extra' => ['new_difficulty' => 'hard'],
        ]);

        RequestServiceFactory::make($request)->reject(['approved_comment' => 'no', 'attachments' => []]);

        $this->assertEquals(RequestStatus::REJECTED, $request->fresh()->status);
        $this->assertEquals(MatterDifficulty::EASY, $matter->fresh()->difficulty);
    }

    public function test_review_report_requires_attachments_and_increments_review_count_once(): void
    {
        $matter = $this->makeMatter(['review_count' => 0]);
        $user = User::factory()->create();

        $this->assertTrue(ReviewReportRequestService::requiresAttachmentsOnCreate());

        $prepared = ReviewReportRequestService::prepareForCreation(['comment' => 'review please'], $matter);
        $this->assertSame(['review_report' => $matter->id], $prepared['extra']);

        $request = MatterRequest::create([
            'matter_id' => $matter->id,
            'request_by' => $user->id,
            'type' => RequestType::REVIEW_REPORT,
            'status' => 'pending',
            'comment' => $prepared['comment'],
            'extra' => $prepared['extra'],
        ]);

        RequestServiceFactory::make($request)->afterCreated();
        $this->assertEquals(1, $matter->fresh()->review_count);

        RequestServiceFactory::make($request)->approve(['approved_comment' => 'ok', 'has_substantive_changes' => true, 'attachments' => []]);

        $matter->refresh();
        $this->assertNotNull($matter->initial_report_at);
        $this->assertTrue($matter->has_substantive_changes);
    }

    public function test_change_distributed_date_extra_is_populated_on_create_and_applied_on_approve(): void
    {
        // Regression: before the refactor, CreateRequestAction never exposed a
        // form field for this type, so extra['proposed_distributed_at'] was
        // never set and approving the request silently did nothing.
        $matter = $this->makeMatter(['distributed_at' => '2026-01-01']);
        $user = User::factory()->create();

        $fields = ChangeDistributedAtRequestService::createFormFields();
        $this->assertNotEmpty($fields);

        $prepared = ChangeDistributedAtRequestService::prepareForCreation([
            'comment' => 'please move the date',
            'proposed_distributed_at' => '2026-05-01',
        ], $matter);

        $this->assertSame(['proposed_distributed_at' => '2026-05-01'], $prepared['extra']);

        $request = MatterRequest::create([
            'matter_id' => $matter->id,
            'request_by' => $user->id,
            'type' => RequestType::CHANGE_DISTRIBUTED_DATE,
            'status' => 'pending',
            'comment' => $prepared['comment'],
            'extra' => $prepared['extra'],
        ]);

        RequestServiceFactory::make($request)->approve(['approved_comment' => 'ok', 'attachments' => []]);

        $this->assertEquals('2026-05-01', $matter->fresh()->distributed_at->toDateString());
    }

    public function test_change_distributed_date_can_only_be_approved_by_the_requester_or_a_super_admin(): void
    {
        $matter = $this->makeMatter();
        $requester = User::factory()->create();
        $stranger = User::factory()->create();
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $request = MatterRequest::create([
            'matter_id' => $matter->id,
            'request_by' => $requester->id,
            'type' => RequestType::CHANGE_DISTRIBUTED_DATE,
            'status' => 'pending',
            'comment' => 'x',
            'extra' => ['proposed_distributed_at' => '2026-05-01'],
        ]);

        $service = RequestServiceFactory::make($request);

        // canBeApproved checks the currently-authenticated user against
        // request_by (matching the original visible() closure's behavior),
        // so each candidate must be the actor, not just the $user argument.
        $this->actingAs($requester);
        $this->assertTrue($service->canBeApproved($requester));

        $this->actingAs($stranger);
        $this->assertFalse($service->canBeApproved($stranger));

        $this->actingAs($superAdmin);
        $this->assertTrue($service->canBeApproved($superAdmin));
    }

    public function test_confirm_report_approve_sets_final_report_memo_date_and_requires_attachments_on_reject(): void
    {
        $matter = $this->makeMatter();
        $user = User::factory()->create();

        $this->assertTrue(ConfirmReportRequestService::rejectionRequiresAttachments());
        $this->assertNotEmpty(ConfirmReportRequestService::approvalFormFields());

        $request = MatterRequest::create([
            'matter_id' => $matter->id,
            'request_by' => $user->id,
            'type' => RequestType::CONFIRM_REPORT,
            'status' => 'pending',
            'comment' => 'x',
        ]);

        RequestServiceFactory::make($request)->approve(['approved_comment' => 'ok', 'attachments' => []]);

        $this->assertNotNull($matter->fresh()->final_report_memo_date);
    }

    public function test_approve_request_action_applies_the_change_distributed_date_side_effect_end_to_end(): void
    {
        $matter = $this->makeMatter(['distributed_at' => '2026-01-01']);
        $requester = User::factory()->create();

        $request = MatterRequest::create([
            'matter_id' => $matter->id,
            'request_by' => $requester->id,
            'type' => RequestType::CHANGE_DISTRIBUTED_DATE,
            'status' => 'pending',
            'comment' => 'x',
            'extra' => ['proposed_distributed_at' => '2026-05-01'],
        ]);

        $this->actingAs($requester);

        Livewire::test(ViewMatterRequest::class, ['record' => $request->id])
            ->callAction(ApproveRequestAction::class, data: ['approved_comment' => 'ok', 'attachments' => []])
            ->assertHasNoActionErrors();

        $this->assertEquals(RequestStatus::APPROVED, $request->fresh()->status);
        $this->assertEquals('2026-05-01', $matter->fresh()->distributed_at->toDateString());
    }

    public function test_auto_confirm_command_applies_the_proposed_date_and_leaves_recent_requests_alone(): void
    {
        // Regression: the scheduled command used a mass update() that flipped the
        // status columns only, so the auto-approval never applied the proposed
        // date to the matter and never notified anyone -- it did strictly less
        // than a manual approval. It now routes through the request service.
        $staleMatter = $this->makeMatter(['number' => '1', 'distributed_at' => '2026-01-01']);
        $freshMatter = $this->makeMatter(['number' => '2', 'distributed_at' => '2026-01-01']);
        $requester = User::factory()->create();

        $stale = MatterRequest::create([
            'matter_id' => $staleMatter->id,
            'request_by' => $requester->id,
            'type' => RequestType::CHANGE_DISTRIBUTED_DATE,
            'status' => 'pending',
            'comment' => 'stale',
            'extra' => ['proposed_distributed_at' => '2026-05-01'],
        ]);
        $stale->forceFill(['created_at' => now()->subDays(3)])->saveQuietly();

        $recent = MatterRequest::create([
            'matter_id' => $freshMatter->id,
            'request_by' => $requester->id,
            'type' => RequestType::CHANGE_DISTRIBUTED_DATE,
            'status' => 'pending',
            'comment' => 'recent',
            'extra' => ['proposed_distributed_at' => '2026-06-01'],
        ]);

        $this->artisan('matter:confirm-receiving')->assertSuccessful();

        // Stale request auto-approved AND its proposed date applied.
        $this->assertEquals(RequestStatus::APPROVED, $stale->fresh()->status);
        $this->assertEquals('2026-05-01', $staleMatter->fresh()->distributed_at->toDateString());

        // A request raised today is left for the assistant to answer.
        $this->assertEquals(RequestStatus::PENDING, $recent->fresh()->status);
        $this->assertEquals('2026-01-01', $freshMatter->fresh()->distributed_at->toDateString());
    }
}
