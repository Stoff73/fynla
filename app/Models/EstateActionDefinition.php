<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EstateActionDefinition extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'source',
        'title_template',
        'description_template',
        'action_template',
        'category',
        'priority',
        'scope',
        'what_if_impact_type',
        'trigger_config',
        'is_enabled',
        'sort_order',
        'notes',
    ];

    protected $casts = [
        'trigger_config' => 'array',
        'is_enabled' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Scope: enabled definitions only.
     */
    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    /**
     * Scope: filter by source (agent or goal).
     */
    public function scopeBySource($query, string $source)
    {
        return $query->where('source', $source);
    }

    /**
     * Find a definition by its unique key.
     */
    public static function findByKey(string $key): ?self
    {
        return static::where('key', $key)->first();
    }

    /**
     * Get all enabled definitions ordered by sort_order.
     */
    public static function getEnabled()
    {
        return static::enabled()->orderBy('sort_order')->get();
    }

    /**
     * Get enabled definitions for a specific source.
     */
    public static function getEnabledBySource(string $source)
    {
        return static::enabled()->bySource($source)->orderBy('sort_order')->get();
    }

    /**
     * Render the title template with variable substitution.
     */
    public function renderTitle(array $vars = []): string
    {
        return $this->renderTemplate($this->title_template, $vars);
    }

    /**
     * Render the description template with variable substitution.
     */
    public function renderDescription(array $vars = []): string
    {
        return $this->renderTemplate($this->description_template, $vars);
    }

    /**
     * Render the action template with variable substitution.
     */
    public function renderAction(array $vars = []): ?string
    {
        if ($this->action_template === null) {
            return null;
        }

        return $this->renderTemplate($this->action_template, $vars);
    }

    /**
     * Replace {placeholder} tokens in a template string.
     */
    private function renderTemplate(string $template, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $template = str_replace('{'.$key.'}', (string) $value, $template);
        }

        return preg_replace('/\{[a-z_]+\}/', '', $template);
    }
}
