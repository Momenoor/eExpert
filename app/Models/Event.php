<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Momenoor\LaravelFullcalendar\Event as LaravelFullcalendarEvent;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Event extends Model implements LaravelFullcalendarEvent
{
    use HasFactory, LogsActivity;


    protected $logAttributes  = [
        'start_date',
        'end_date',
        'all_day',
        'title',
        'type',
        'url',
        'request_by',
        'request_at',
        'approved_by',
        'approved_at',
    ];

    protected $fillable = [
        'start_date',
        'end_date',
        'all_day',
        'title',
        'type',
        'url',
        'request_by',
        'request_at',
        'approved_by',
        'approved_at',
    ];

    protected $dates = [
        'start_date',
        'end_date',
        'request_at',
        'approved_at',
        'created_at',
        'updated_at',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($query) {
            $query->request_by = request()->get('request_by') ?: auth()->id();
            $query->request_at = now();
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }
    /**
     * Get the event's id number
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get the event's title
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Is it an all day event?
     *
     * @return bool
     */
    public function isAllDay()
    {
        return (bool)$this->all_day;
    }

    /**
     * Get the start time
     *
     * @return DateTime
     */
    public function getStart()
    {
        return $this->start_date;
    }

    /**
     * Get the end time
     *
     * @return DateTime
     */
    public function getEnd()
    {
        return $this->end_date;
    }

   /*  public function getEventOptions()
    {
        return [
            'color' => 'bg-danger',
        ];
    } */
}
