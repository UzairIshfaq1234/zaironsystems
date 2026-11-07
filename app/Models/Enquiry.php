<?php

namespace App\Models;

use App\Support\Content;
use Illuminate\Database\Eloquent\Model;

/** A "Start a project" submission from the contact page. */
class Enquiry extends Model
{
    public const STATUSES = ['new', 'contacted', 'in_progress', 'won', 'lost'];

    protected $fillable = [
        'name', 'email', 'company', 'phone', 'services', 'service', 'plan', 'budget', 'details',
        'brief_path', 'brief_name', 'ip_address', 'user_agent', 'status', 'notes',
    ];

    protected $attributes = ['status' => 'new'];

    /** Readable names for the slug/ids stored on the enquiry (used in emails). */
    public function serviceTitle(): ?string
    {
        return $this->service ? (collect(Content::get('services')['items'])->firstWhere('slug', $this->service)['title'] ?? $this->service) : null;
    }

    public function planTitle(): ?string
    {
        return $this->plan ? (collect(Content::get('engagement')['plans'])->firstWhere('id', $this->plan)['title'] ?? $this->plan) : null;
    }

    /** @return list<string> */
    public function needsLabels(): array
    {
        $labels = collect(Content::get('services')['formOptions'])->pluck('label', 'value');

        return array_map(fn ($v) => $labels[$v] ?? $v, $this->services ?? []);
    }

    protected $hidden = ['ip_address', 'user_agent', 'brief_path'];

    protected function casts(): array
    {
        return ['services' => 'array'];
    }
}
