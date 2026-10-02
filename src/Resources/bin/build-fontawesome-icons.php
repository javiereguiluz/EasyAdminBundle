#!/usr/bin/env php
<?php

// Copies the SVG files of FontAwesome Free (installed with Yarn) into
// assets/icons/fontawesome/ so EasyAdmin can render FontAwesome icons as inline
// SVG without loading the FontAwesome CSS and webfonts. It also generates the
// map of legacy icon names (FontAwesome 4 and 5) to the current icon names.
//
// Run it again (make build-fontawesome-icons) after updating the
// @fortawesome/fontawesome-free version in package.json.

const STYLES = ['solid', 'regular', 'brands'];

$projectDir = \dirname(__DIR__, 3);
$packageDir = $projectDir.'/node_modules/@fortawesome/fontawesome-free';
$outputDir = $projectDir.'/assets/icons/fontawesome';

if (!is_dir($packageDir)) {
    fail(sprintf('The FontAwesome package was not found in "%s". Run "yarn install" first.', $packageDir));
}

$iconFamilies = json_decode(readFileContents($packageDir.'/metadata/icon-families.json'), associative: true, flags: \JSON_THROW_ON_ERROR);

$iconNamesByStyle = copySvgFiles($packageDir, $outputDir);
$aliases = buildAliases($iconFamilies, $iconNamesByStyle);
$shims = buildShims(readFileContents($packageDir.'/css/v4-shims.css'), $iconFamilies, $iconNamesByStyle);

file_put_contents($outputDir.'/aliases.php', exportAliasesFile($aliases, $shims));
copy($packageDir.'/LICENSE.txt', $outputDir.'/LICENSE.txt');

printf("Copied %d SVG icons, %d aliases and %d FontAwesome 4 names to %s\n", array_sum(array_map('count', $iconNamesByStyle)), \count($aliases), \count($shims), $outputDir);

/**
 * @return array<string, array<string, true>> the icon names that exist in each style
 */
function copySvgFiles(string $packageDir, string $outputDir): array
{
    $iconNamesByStyle = [];
    foreach (STYLES as $style) {
        $styleOutputDir = $outputDir.'/'.$style;
        if (is_dir($styleOutputDir)) {
            array_map('unlink', glob($styleOutputDir.'/*.svg'));
        } else {
            mkdir($styleOutputDir, 0777, true);
        }

        $iconNamesByStyle[$style] = [];
        foreach (glob(sprintf('%s/svgs/%s/*.svg', $packageDir, $style)) as $svgFilePath) {
            $svgContents = trim(readFileContents($svgFilePath));
            if (!str_starts_with($svgContents, '<svg ') || str_contains($svgContents, 'fill=')) {
                fail(sprintf('The "%s" file has an unexpected format.', $svgFilePath));
            }

            // makes icons use the text color of their container, as FontAwesome webfonts do
            $svgContents = preg_replace('/^<svg /', '<svg fill="currentColor" ', $svgContents);

            $iconName = basename($svgFilePath, '.svg');
            file_put_contents(sprintf('%s/%s.svg', $styleOutputDir, $iconName), $svgContents);
            $iconNamesByStyle[$style][$iconName] = true;
        }
    }

    return $iconNamesByStyle;
}

/**
 * Icon names of FontAwesome 5 (and some of FontAwesome 6) that were renamed.
 *
 * @param array<string, array<string, mixed>> $iconFamilies
 * @param array<string, array<string, true>>  $iconNamesByStyle
 *
 * @return array<string, string> alias => current icon name
 */
function buildAliases(array $iconFamilies, array $iconNamesByStyle): array
{
    $aliases = [];
    foreach ($iconFamilies as $iconName => $icon) {
        if (!isFreeIcon($iconName, $iconNamesByStyle)) {
            continue;
        }

        foreach ($icon['aliases']['names'] ?? [] as $alias) {
            if (isset($aliases[$alias]) && $aliases[$alias] !== $iconName) {
                fail(sprintf('The "%s" alias points to both "%s" and "%s" icons.', $alias, $aliases[$alias], $iconName));
            }

            $aliases[$alias] = (string) $iconName;
        }
    }

    ksort($aliases);

    return $aliases;
}

/**
 * Icon names of FontAwesome 4. The v4-shims.css file is used instead of
 * metadata/shims.yml because the YAML file doesn't include the brand icons
 * whose name didn't change (e.g. 'fa fa-github').
 *
 * @param array<string, array<string, mixed>> $iconFamilies
 * @param array<string, array<string, true>>  $iconNamesByStyle
 *
 * @return array<string, array{name: string, style: ?string}> v4 name => current icon name and style (null when the requested style is kept)
 */
function buildShims(string $shimsCss, array $iconFamilies, array $iconNamesByStyle): array
{
    $iconNamesByUnicode = [];
    foreach ($iconFamilies as $iconName => $icon) {
        if (isFreeIcon($iconName, $iconNamesByStyle)) {
            $iconNamesByUnicode[$icon['unicode']] = (string) $iconName;
        }
    }

    preg_match_all('/((?:\.fa\.fa-[a-z0-9-]+\s*,?\s*)+)\{([^}]*)\}/', $shimsCss, $rules, \PREG_SET_ORDER);

    $shimsProperties = [];
    foreach ($rules as [, $selectors, $declarations]) {
        preg_match_all('/\.fa\.fa-([a-z0-9-]+)/', $selectors, $selectorNames);
        foreach ($selectorNames[1] as $v4Name) {
            $shimsProperties[$v4Name] ??= [];
            if (preg_match('/--fa:\s*"\\\\([0-9a-f]+)"/', $declarations, $match)) {
                $shimsProperties[$v4Name]['unicode'] = $match[1];
            }
            if (str_contains($declarations, 'Font Awesome 6 Brands')) {
                $shimsProperties[$v4Name]['style'] = 'brands';
            } elseif (str_contains($declarations, 'font-weight: 400')) {
                $shimsProperties[$v4Name]['style'] = 'regular';
            }
        }
    }

    $shims = [];
    foreach ($shimsProperties as $v4Name => $properties) {
        $iconName = isset($properties['unicode']) ? ($iconNamesByUnicode[$properties['unicode']] ?? null) : $v4Name;
        if (null === $iconName) {
            fail(sprintf('The "%s" icon of FontAwesome 4 uses an unknown unicode value (%s).', $v4Name, $properties['unicode']));
        }

        $style = $properties['style'] ?? null;
        if (!isset($iconNamesByStyle[$style ?? 'solid'][$iconName])) {
            fail(sprintf('The "%s" icon of FontAwesome 4 points to the "%s" icon, which does not exist in the "%s" style.', $v4Name, $iconName, $style ?? 'solid'));
        }

        if ($iconName === $v4Name && null === $style) {
            continue;
        }

        $shims[$v4Name] = ['name' => $iconName, 'style' => $style];
    }

    ksort($shims);

    return $shims;
}

/**
 * @param array<string, array<string, true>> $iconNamesByStyle
 */
function isFreeIcon(string $iconName, array $iconNamesByStyle): bool
{
    foreach ($iconNamesByStyle as $iconNames) {
        if (isset($iconNames[$iconName])) {
            return true;
        }
    }

    return false;
}

/**
 * @param array<string, string>                              $aliases
 * @param array<string, array{name: string, style: ?string}> $shims
 */
function exportAliasesFile(array $aliases, array $shims): string
{
    $lines = [
        '<?php',
        '',
        '// this file is generated by src/Resources/bin/build-fontawesome-icons.php; don\'t edit it',
        '',
        'return [',
        '    \'aliases\' => [',
    ];
    foreach ($aliases as $alias => $iconName) {
        $lines[] = sprintf("        '%s' => '%s',", $alias, $iconName);
    }
    $lines[] = '    ],';
    $lines[] = '    \'shims\' => [';
    foreach ($shims as $v4Name => $shim) {
        $lines[] = sprintf("        '%s' => ['name' => '%s', 'style' => %s],", $v4Name, $shim['name'], null === $shim['style'] ? 'null' : "'".$shim['style']."'");
    }
    $lines[] = '    ],';
    $lines[] = '];';

    return implode("\n", $lines)."\n";
}

function readFileContents(string $filePath): string
{
    $contents = @file_get_contents($filePath);
    if (false === $contents) {
        fail(sprintf('The "%s" file cannot be read.', $filePath));
    }

    return $contents;
}

function fail(string $message): never
{
    fwrite(\STDERR, $message."\n");
    exit(1);
}
