<?php

namespace App\Models;

use Database\Factories\JobApplicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A job application submitted from the careers page. */
class JobApplication extends Model
{
    /** @use HasFactory<JobApplicationFactory> */
    use HasFactory;

    public const STATUS_NEW = 'new';

    public const STATUSES = ['new', 'reviewing', 'shortlisted', 'rejected', 'hired'];

    protected $fillable = [
        'job_opening_id', 'name', 'email', 'phone', 'links', 'message',
        'cv_path', 'cv_name', 'status', 'notes', 'ip_address', 'user_agent',
    ];

    protected $attributes = ['status' => self::STATUS_NEW];

    protected $hidden = ['ip_address', 'user_agent', 'cv_path'];

    /** @return BelongsTo<JobOpening, $this> */
    public function opening(): BelongsTo
    {
        return $this->belongsTo(JobOpening::class, 'job_opening_id');
    }
}
