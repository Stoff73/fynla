<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Constants\GateRoutes;
use App\Constants\QuerySchemas;
use App\Models\AiConversation;

/**
 * "Take me to my tax strategy": navigation decided on the server, for every
 * surface, before the model (T6, walk fix plan B2.5; F3, F4).
 *
 * The web chat used to match keywords in the browser, so "Show the working"
 * opened a page and the request never reached the server, while /m and
 * native had no navigation at all. Now one plain command, with no question
 * in it, opens the page it names: the rest of the message after the command
 * must be a page name from GateRoutes::NAVIGATION_NAMES, so words match
 * whole. The request and the reply are saved like any turn.
 */
final class NavigationCommand
{
    public const SURFACE_WEB = 'web';

    public const SURFACE_MOBILE = 'm';

    public const SURFACE_NATIVE = 'native';

    private const COMMAND = '/^(?:(?:ok|okay|right|so|now|please|fyn)\b[,\s]*)*'
        .'(?:take\s+me\s+to|go\s+to|navigate\s+to|open|show\s+me|bring\s+up)\s+'
        .'(?:(?:my|the|our)\s+)?'
        .'(?<page>.+?)'
        .'(?:\s+(?:page|screen|section|tab))?'
        .'(?:[,\s]+please)?$/u';

    /** The page a plain navigation command names, or null for anything else. */
    public static function match(string $message): ?string
    {
        $text = mb_strtolower(trim($message));
        if ($text === '' || str_contains($text, '?') || QuerySchemas::isAdviceShaped($text)) {
            return null;
        }

        $text = rtrim($text, " .!\t\n");
        if (preg_match(self::COMMAND, $text, $matches) !== 1) {
            return null;
        }

        $page = preg_replace('/\s+/', ' ', trim($matches['page']));
        foreach (GateRoutes::NAVIGATION_NAMES as $destination => $names) {
            if (in_array($page, $names, true)) {
                return $destination;
            }
        }

        return null;
    }

    /**
     * Run the navigation turn: save the request (unless it is already saved,
     * as a queued turn is) and the reply, then send the surface its own page
     * with the existing `navigation` event. A page the surface does not have
     * gets a plain reply and no navigation.
     */
    public function handle(
        AiConversation $conversation,
        string $message,
        string $destination,
        string $surface,
        bool $persistUserMessage = true,
    ): \Generator {
        $page = GateRoutes::resolve($destination);
        $route = $surface === self::SURFACE_WEB ? $page['web'] : $page['mobile'];
        $reply = $route !== null
            ? "Opening {$page['label']}."
            : "{$page['label']} is only on the website for now.";

        if ($persistUserMessage) {
            $conversation->messages()->create(['role' => 'user', 'content' => $message, 'persona' => 'advice']);
        }
        $conversation->messages()->create(['role' => 'assistant', 'content' => $reply, 'persona' => 'advice']);

        yield ['type' => 'content', 'text' => $reply];

        if ($route !== null) {
            yield [
                'type' => 'navigation',
                'route_path' => $route,
                'destination' => GateRoutes::destination($destination),
                // Empty: the reply above is the message; the web store adds a
                // second bubble for a navigation event with a description.
                'description' => '',
            ];
        }

        yield ['type' => 'done'];
    }
}
