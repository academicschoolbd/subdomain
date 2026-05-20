<?php
declare(strict_types=1);

/**
 * v4.5 — Admin-controllable home / brand theme color.
 *
 * The admin picks a primary brand hex from the platform-settings pane.
 * We then derive the dark-mode primary, the -50 / -100 tint variants
 * and the link color, and emit them as CSS variables overriding
 * style.css's :root + html[data-theme="dark"] tokens.
 *
 * Emit `theme_emit_head_style()` inside every page <head>, AFTER the
 * style.css <link>, so the per-tenant brand color always wins.
 */

const THEME_DEFAULT_PRIMARY      = '#0f766e'; // teal-700
const THEME_DEFAULT_PRIMARY_DARK = '#14b8a6'; // teal-500 (used in dark theme)

/** Sanitize / normalize a "#rrggbb" hex string. Returns the default on bad input. */
function theme_norm_hex(?string $hex, string $fallback = THEME_DEFAULT_PRIMARY): string
{
    if (!is_string($hex)) return $fallback;
    $hex = strtolower(trim($hex));
    if ($hex === '') return $fallback;
    if ($hex[0] !== '#') $hex = '#' . $hex;
    if (preg_match('/^#([0-9a-f]{3})$/', $hex, $m)) {
        $h = $m[1];
        $hex = '#' . $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
    }
    if (!preg_match('/^#[0-9a-f]{6}$/', $hex)) return $fallback;
    return $hex;
}

/** Convert "#rrggbb" -> [r,g,b] ints (0-255). */
function theme_hex_to_rgb(string $hex): array
{
    $hex = ltrim($hex, '#');
    return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
}

/** Convert [r,g,b] back to "#rrggbb". */
function theme_rgb_to_hex(array $rgb): string
{
    [$r, $g, $b] = $rgb;
    return sprintf('#%02x%02x%02x',
        max(0, min(255, (int)$r)),
        max(0, min(255, (int)$g)),
        max(0, min(255, (int)$b)));
}

/** Mix `$hex` with `$mix` by `$weight` (0..1, where 1 = pure $mix). */
function theme_mix(string $hex, string $mix, float $weight): string
{
    [$r1, $g1, $b1] = theme_hex_to_rgb($hex);
    [$r2, $g2, $b2] = theme_hex_to_rgb($mix);
    $w = max(0.0, min(1.0, $weight));
    return theme_rgb_to_hex([
        $r1 + ($r2 - $r1) * $w,
        $g1 + ($g2 - $g1) * $w,
        $b1 + ($b2 - $b1) * $w,
    ]);
}

/** Darken: mix toward black. */
function theme_darken(string $hex, float $w): string { return theme_mix($hex, '#000000', $w); }
/** Lighten: mix toward white. */
function theme_lighten(string $hex, float $w): string { return theme_mix($hex, '#ffffff', $w); }

/** "rgba(r,g,b,a)" string from a hex. */
function theme_rgba(string $hex, float $alpha): string
{
    [$r, $g, $b] = theme_hex_to_rgb($hex);
    return sprintf('rgba(%d,%d,%d,%.3f)', $r, $g, $b, max(0.0, min(1.0, $alpha)));
}

/**
 * Read the configured theme colors from platform_settings (with safe
 * defaults if the DB isn't available yet). Returns:
 *
 *   ['primary' => '#hex', 'primary_dark' => '#hex']
 *
 * Both are valid 6-digit hex strings.
 */
function theme_load_colors(?array $CONFIG): array
{
    $primary     = THEME_DEFAULT_PRIMARY;
    $primaryDark = THEME_DEFAULT_PRIMARY_DARK;
    if (!is_array($CONFIG) || !function_exists('settings_get_all')) {
        return ['primary' => $primary, 'primary_dark' => $primaryDark];
    }
    try {
        $s = settings_get_all($CONFIG);
        if (!empty($s['theme_primary_hex'])) {
            $primary = theme_norm_hex($s['theme_primary_hex'], THEME_DEFAULT_PRIMARY);
        }
        if (!empty($s['theme_primary_dark_hex'])) {
            $primaryDark = theme_norm_hex($s['theme_primary_dark_hex'], THEME_DEFAULT_PRIMARY_DARK);
        } else {
            // Auto-derive a dark-theme variant if the admin hasn't picked one.
            // Lighten the primary by 30% so it stays readable on a near-black surface.
            $primaryDark = theme_lighten($primary, 0.30);
        }
    } catch (Throwable $_e) { /* fall through to defaults */ }
    return ['primary' => $primary, 'primary_dark' => $primaryDark];
}

/**
 * Emit a <style id="theme-vars"> block that overrides the brand-color
 * CSS variables. Must be emitted INSIDE the <head>, AFTER the main
 * style.css link, so it wins the cascade.
 *
 * Tolerates a missing $CONFIG (e.g. when the DB isn't reachable yet on
 * a fresh install) — falls back to the platform defaults so pages still
 * render correctly.
 */
function theme_emit_head_style(?array $CONFIG = null): void
{
    $c = theme_load_colors($CONFIG);
    $p = $c['primary'];
    $pd = $c['primary_dark'];

    // Derived shades for the light theme.
    $p700  = theme_darken($p, 0.12);
    $p50   = theme_rgba($p, 0.08);
    $p100  = theme_rgba($p, 0.18);

    // Derived shades for the dark theme.
    $pd700 = theme_darken($pd, 0.10);
    $pd50  = theme_rgba($pd, 0.12);
    $pd100 = theme_rgba($pd, 0.22);

    // Browser chrome theme-color follows the primary brand for the light
    // theme — keeps Chrome / Safari address bars in-brand on phones.
    $metaLight = htmlspecialchars($p, ENT_QUOTES, 'UTF-8');

    echo "<style id=\"theme-vars\">\n";
    echo ":root{--c-primary:$p;--c-primary-700:$p700;--c-primary-50:$p50;--c-primary-100:$p100;--c-link:$p;}\n";
    echo "html[data-theme=\"dark\"]{--c-primary:$pd;--c-primary-700:$pd700;--c-primary-50:$pd50;--c-primary-100:$pd100;--c-link:$pd;}\n";
    echo "</style>\n";
    echo "<meta name=\"theme-color\" content=\"$metaLight\" media=\"(prefers-color-scheme: light)\" />\n";
    echo "<meta name=\"theme-color\" content=\"#0b1220\" media=\"(prefers-color-scheme: dark)\" />\n";
}
