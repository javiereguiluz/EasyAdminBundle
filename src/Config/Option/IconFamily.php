<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Config\Option;

/**
 * The icon sets that can be used in the backend with Assets::useIconFamily().
 * Except for FontAwesome (whose icons are included in EasyAdmin), the values are
 * the prefixes used by Iconify (https://icon-sets.iconify.design/) for each icon set.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
enum IconFamily: string
{
    case FontAwesome = 'fontawesome';
    case Tabler = 'tabler';
    case Lucide = 'lucide';
    case Heroicons = 'heroicons';
    case Phosphor = 'ph';
    case BootstrapIcons = 'bi';
    case MaterialSymbols = 'material-symbols';
    case Remix = 'ri';
}
