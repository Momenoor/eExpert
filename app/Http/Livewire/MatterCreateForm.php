<?php

namespace App\Http\Livewire;

use App\Jobs\CreateMatter;
use Livewire\Component;
use App\Models\Court;
use App\Models\Expert;
use App\Models\Matter;
use App\Models\Party;
use App\Models\Type;
use App\Models\User;
use App\Services\ExpertService;
use App\Services\Money;
use App\Services\NumberFormatterService;
use Illuminate\Foundation\Bus\DispatchesJobs;

class MatterCreateForm extends Component
{

    use DispatchesJobs;


    // Main Model Definations
    public $matter;
    public $parties = [];
    public $experts = [];
    public $marketing = [];
    public $hasMarketingCommission;
    public $hasExternalCommission;
    public $claims = [];
    public $claim;
    public $isNew = true;


    // Indexes for clonable fields
    public $i = 1;
    public $n = 1;
    public $x = 1;

    // predefined data passed to the form for population
    public $partyTypes;
    public $claimsTypes;
    public $expertsList;
    public $assistantsList;
    public $courtsList;
    public $typesList;
    public $partiesList;
    public $advocatesList;
    public $committeesList;
    public $marketersList;
    public $externalMarketersList;
    public $committeeChoiceValue;

    // new subparty form
    public $newsubparty = [];
    public $newexpert = [];

    // Events Listners
    protected $listeners = [
        'showAddSubPartyButton'
    ];


    protected $rules = [
        'matter.year' => 'required|min:4|max:4|date_format:Y',
        'matter.number' => 'required',
        'matter.received_date' => 'required|date',
        'matter.next_session_date' => 'required|date',
        'matter.commissioning' => 'required',
        'matter.court_id' => 'required|exists:courts,id',
        'matter.type_id' => 'required|exists:types,id',
        'matter.expert_id' => 'required|exists:experts,id',
        'matter.level_id' => 'required',
        'experts.committee' => 'required_if:matter.commissioning,committee',
        'experts.assistant' => 'required|exists:experts,id',
        'parties.*.type' => 'required',
        'parties.*.name' => 'required',
        'parties.*.phone' => 'numeric',
        'parties.*.email' => 'email',
        'parties.*.subParties.*' => 'required',
        'matter.external_commission_percent' => 'required_if:hasExternalCommission,1|numeric',
        'marketing.external_markter.id' => 'required_if:hasExternalCommission,1',
        'marketing.marketer.id' => 'required_if:hasMarketingCommission,1',

    ];




    public function mount()
    {
        $this->expertsList = Expert::whereIn('category', ['main', 'certified'])->get();
        $this->assistantsList = Expert::whereIn('category', ['main', 'certified', 'assistant'])->get();
        $this->courtsList = Court::get(['id', 'name'])->toArray();
        $this->typesList = Type::get(['id', 'name'])->toArray();
        $this->advocatesList = Party::whereIn('type', ['office', 'advocate', 'advisor'])->get(['id', 'name'])->toArray();
        $this->externalMarketersList = Party::where('type', 'external_marketer')->get(['id', 'name'])->toArray();
        $this->committeesList = Expert::CommitteesList()->get();
        $this->marketersList = User::where('category', 'staff')->get(['id', 'display_name'])->toArray();
        $this->levelList = config('system.level');
        $this->committeeChoiceValue = Matter::COMMITTEE;
        $this->partyTypes = config('system.parties.type');
        $this->claimsTypes = config('system.claims.types');
        $this->addParty();
    }
    public function render()
    {

        return view('livewire.matter-create-form');
    }

    public function addParty()
    {
        $this->parties[$this->i] = [
            'showAddSubPartyButton' => false,
            'subParties' => [],
        ];
        $this->i++;
    }

    public function showAddSubPartyButton($index, $value)
    {

        $this->parties[$index]['showAddSubPartyButton'] = $this->partyTypes[$value]['showAddPartyButton'];
    }

    public function removeParty($index)
    {
        unset($this->parties[$index]);
    }

    public function addSubParty($parentIndex)
    {
        $this->parties[$parentIndex]['subParties'][$this->n] = null;
        $this->n++;
    }

    public function removeSubParty($parentIndex, $index)
    {
        unset($this->parties[$parentIndex]['subParties'][$index]);
    }

    public function removeAllSubPartyItem($parentIndex)
    {
        $this->parties[$parentIndex]['subParties'] = [];
    }

    public function addClaim()
    {
        $this->x++;

        $validatedData = $this->validate([
            'claim.amount' => 'required',
            'claim.type' => 'required',
            'claim.recurring' => 'required',
        ]);

        if (key_exists('taxable', $this->claim) && $this->claim['taxable']) {
            $vatRate = floatval(config('system.vat.rate'));
            $taxableData = [
                'type' => $this->claimsTypes['vat'],
                'amount' => app(Money::class)->getFormattedNumber(data_get($validatedData, 'claim.amount') * $vatRate / 100),
                'recurring' => $this->claimsTypes['recurring']['values'][data_get($validatedData, 'claim.recurring')]
            ];
            $this->claims[$this->n] = $taxableData;
            $this->n++;
        }

        if (!in_array($this->matter['expert_id'], config('system.experts.main'))) {
            $shareRate = floatval(config('system.experts.office_share.rate'));
            $shareData = [
                'type' => $this->claimsTypes['office_share'],
                'amount' => app(Money::class)->getFormattedNumber(data_get($validatedData, 'claim.amount') * $shareRate / 100),
                'recurring' => $this->claimsTypes['recurring']['values'][data_get($validatedData, 'claim.recurring')]
            ];
            $this->claims[$this->n] = $shareData;
            $this->n++;
        }

        $this->claims[$this->n] = [
            'amount' => app(Money::class)->getFormattedNumber(data_get($validatedData, 'claim.amount')),
            'type' => $this->claimsTypes[data_get($validatedData, 'claim.type')],
            'recurring' => $this->claimsTypes['recurring']['values'][data_get($validatedData, 'claim.recurring')]
        ];

        $this->n++;
        $this->claim = [];
    }

    public function removeClaim($index)
    {
        unset($this->claims[$index]);
    }

    public function save()
    {
        $this->validate();
        $data = [
            'matter' => $this->matter,
            'claims' => $this->claims,
            'parties' => $this->parties,
            'experts' => $this->experts,
            'marketing' => $this->marketing,
        ];


        /* dd($data); */
        $this->dispatch(new CreateMatter($data));

        return redirect(route('matter.show', session('last_inserted_matter')))->with('toast_success', __('app.matter-successfully-added'));
    }

    public function addNewSubparty()
    {
        $validatedData = $this->validate([
            'newsubparty.name' => 'required|unique:parties,parties.name',
            'newsubparty.phone' => 'required',
            'newsubparty.email' => 'required|email',
            'newsubparty.fax' => 'required',
            'newsubparty.type' => 'required|in:office,advocate',
            'newsubparty.address' => 'required',
        ]);

        Party::create($validatedData['newsubparty']);
        $this->advocatesList = Party::whereIn('type', ['office', 'advocate', 'advisor'])->get(['id', 'name'])->toArray();
        $this->resetNewSubparty();
        $this->emit('closeModal');
    }

    public function resetNewSubparty()
    {
        $this->resetValidation();
        $this->newsubparty = [];
    }

    public function addNewExpert()
    {
        request()->merge($this->newexpert);
        (new ExpertService())->save(request());
        /* $validatedData = $this->validate([
            'newexpert.name' => 'required|unique:experts,experts.name',
            'newexpert.phone' => 'required',
            'newexpert.email' => 'required|email',
            'newexpert.field' => 'required',
            'newexpert.category' => 'required|in:main,certified,assistant,external,external-assistant',
        ]);

        Expert::create($validatedData['newexpert']); */
        $this->committeesList = Expert::CommitteesList()->get();
        $this->resetNewExpert();
        $this->emit('closeModal');
    }

    public function resetNewExpert()
    {
        $this->resetValidation();
        $this->newexpert = [];
    }

    public function resetForm()
    {
        $this->resetValidation();
        $this->matter = null;
        $this->parties = [];
        $this->experts = [];
        $this->marketing = [];
        $this->hasMarketingCommission = null;
        $this->hasExternalCommission = null;
        $this->claims = [];
        $this->claim = null;
    }
}
