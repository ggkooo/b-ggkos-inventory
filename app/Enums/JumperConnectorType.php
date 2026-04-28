<?php

namespace App\Enums;

enum JumperConnectorType: string
{
    case MaleToMale = 'Macho-Macho';
    case MaleToFemale = 'Macho-Femea';
    case FemaleToFemale = 'Femea-Femea';
}
