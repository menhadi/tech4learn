<?php
namespace App\Http\Controllers;

/** Private invocation only: context comes from the checked central authoring service.
 * No routes target this class and no native user receives global administrator rights.
 * Native LanguageController retains validation, persistence and duplicate rules.
 */
final class Tech4LearnCentralLanguageController extends LanguageController
{
    public function __construct(private readonly int $centralOwner) {
        abort_unless($centralOwner>0,403);
    }
    protected function isPlatformAdmin():bool {return true;}
    protected function platformOrganizationId():?int {return $this->centralOwner;}
}
