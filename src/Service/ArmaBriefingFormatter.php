<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Service;

final class ArmaBriefingFormatter
{
    /**
     * Converts the small safe subset of Arma structured text used by briefings
     * into HTML. Unknown tags and unsafe attributes are discarded.
     */
    public function format(string $body): string
    {
        $tokens = preg_split('~(<[^>]+>)~u', $body, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($tokens === false) {
            return htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        $html = '';
        $openTags = [];
        foreach ($tokens as $token) {
            if ($token === '') {
                continue;
            }
            if ($token[0] !== '<') {
                $html .= htmlspecialchars($token, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                continue;
            }
            if (preg_match('~^<br\s*/?>$~i', $token) === 1) {
                $html .= '<br>';
                continue;
            }
            if (preg_match('~^<(font|t)\b([^>]*)>$~i', $token, $match) === 1) {
                $styles = [];
                if (($color = $this->attribute($match[2], 'color')) !== null
                    && preg_match('/^#[0-9a-f]{6}$/i', $color) === 1
                ) {
                    $styles[] = 'color:' . strtolower($color);
                }
                if (strtolower($match[1]) === 't') {
                    if (($size = $this->attribute($match[2], 'size')) !== null
                        && is_numeric($size)
                        && (float) $size >= 0.5
                        && (float) $size <= 3.0
                    ) {
                        $styles[] = 'font-size:' . rtrim(rtrim(number_format((float) $size, 2, '.', ''), '0'), '.') . 'em';
                    }
                    if (($align = strtolower((string) $this->attribute($match[2], 'align')))
                        && in_array($align, ['left', 'center', 'right', 'justify'], true)
                    ) {
                        $styles[] = 'text-align:' . $align;
                    }
                }
                $html .= '<span class="cc-frago__formatted"' . ($styles === [] ? '' : ' style="' . implode(';', $styles) . '"') . '>';
                $openTags[] = strtolower($match[1]);
                continue;
            }
            if (preg_match('~^</(font|t)>$~i', $token, $match) === 1) {
                if ($openTags !== [] && end($openTags) === strtolower($match[1])) {
                    array_pop($openTags);
                    $html .= '</span>';
                }
                continue;
            }
            if (preg_match('~^<marker\b([^>]*)>$~i', $token, $match) === 1) {
                $name = $this->attribute($match[1], 'name');
                $html .= '<span class="cc-frago__marker"' . ($name === null ? '' : ' title="' . htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"') . '>';
                $openTags[] = 'marker';
                continue;
            }
            if (preg_match('~^</marker>$~i', $token) === 1 && $openTags !== [] && end($openTags) === 'marker') {
                array_pop($openTags);
                $html .= '</span>';
            }
        }

        while ($openTags !== []) {
            array_pop($openTags);
            $html .= '</span>';
        }

        $html = preg_replace('~(?:\s*<br>\s*){3,}~', '<br><br>', $html) ?? $html;

        return trim($html);
    }

    private function attribute(string $attributes, string $name): ?string
    {
        if (preg_match(sprintf('~\b%s\s*=\s*(["\'])(.*?)\1~i', preg_quote($name, '~')), $attributes, $match) !== 1) {
            return null;
        }

        return trim($match[2]);
    }
}
