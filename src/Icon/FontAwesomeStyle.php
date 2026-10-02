<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Icon;

/**
 * The styles of FontAwesome Free; their values are the names of the
 * directories that store the SVG files of each style.
 *
 * @internal
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
enum FontAwesomeStyle: string
{
    case Solid = 'solid';
    case Regular = 'regular';
    case Brands = 'brands';

    public function getPrefix(): string
    {
        return match ($this) {
            self::Solid => 'fas',
            self::Regular => 'far',
            self::Brands => 'fab',
        };
    }
}
