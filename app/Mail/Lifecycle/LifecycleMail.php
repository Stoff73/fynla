<?php

declare(strict_types=1);

namespace App\Mail\Lifecycle;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Base class for every lifecycle email. Provides the Google Analytics
 * UTM-tagging helper used by each CTA URL so click attribution is consistent
 * across the whole sequence.
 *
 * Convention:
 *   utm_source   = "email"
 *   utm_medium   = "lifecycle"
 *   utm_campaign = the specific email slug (e.g. "welcome", "get-started")
 *   utm_content  = the CTA identifier (e.g. "hero-cta", "fyn-help", "protection-tile")
 */
abstract class LifecycleMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Each concrete Mailable returns its utm_campaign slug.
     */
    abstract protected function utmCampaign(): string;

    /**
     * Append Google Analytics UTM parameters to a URL.
     */
    protected function utm(string $url, string $content): string
    {
        $params = [
            'utm_source'   => 'email',
            'utm_medium'   => 'lifecycle',
            'utm_campaign' => $this->utmCampaign(),
            'utm_content'  => $content,
        ];
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . http_build_query($params);
    }
}
