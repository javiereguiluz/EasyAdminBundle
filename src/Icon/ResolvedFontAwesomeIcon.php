<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Icon;

/**
 * @internal
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
final readonly class ResolvedFontAwesomeIcon
{
    /**
     * @param string[] $classes the CSS classes to add to the <svg> element
     */
    public function __construct(
        public FontAwesomeStyle $style,
        public string $name,
        public string $path,
        public string $svgContents,
        public array $classes,
    ) {
    }
}
