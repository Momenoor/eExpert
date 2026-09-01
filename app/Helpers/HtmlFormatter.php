<?php

namespace App\Helpers;

class HtmlFormatter
{
    /**
     * Escape user-supplied text, then turn bare URLs into anchors.
     *
     * Used by infolist entries that render with ->html(). The escape MUST come
     * first: the state is user-supplied (calendar event descriptions), so
     * rendering it raw is stored XSS.
     */
    public static function linkify(?string $state): ?string
    {
        if (blank($state)) {
            return $state;
        }

        $safeState = e($state);

        $pattern = '~(?<!@)\b(?:https?://|www\.)[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/))~';

        return preg_replace_callback($pattern, function (array $matches): string {
            $url = $matches[0];
            $href = str_starts_with($url, 'www.') ? "https://{$url}" : $url;
            $class = str_contains($url, 'teams.microsoft.com')
                ? 'text-primary-600 font-bold underline'
                : 'text-primary-600 underline';

            return '<a href="'.$href.'" target="_blank" rel="noopener noreferrer" class="'.$class.'">'.$url.'</a>';
        }, $safeState);
    }
}
