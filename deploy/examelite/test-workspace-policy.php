<?php
require __DIR__.'/Tech4LearnWorkspacePolicy.php';
use App\Support\Tech4LearnWorkspacePolicy as Policy;
function expect($actual, $expected): void {
    if ($actual !== $expected) throw new RuntimeException('Workspace policy assertion failed.');
}
foreach (Policy::FEATURES as $feature) expect(Policy::mayUse($feature, []), true);
expect(Policy::mayUse('questions', ['questions']), false);
expect(Policy::mayUse('exams', ['questions']), true);
expect(Policy::mayUse('unknown', []), false);
expect(Policy::restrictions(['taking','questions','taking']), ['questions','taking']);
foreach ([null, 'all', ['all'], [['questions']], [1]] as $invalid) {
    try { Policy::restrictions($invalid); throw new RuntimeException('Invalid restriction accepted.'); }
    catch (InvalidArgumentException $expected) {}
}
expect(Policy::editAction(1, 2, 1, false), 'copy');
expect(Policy::editAction(1, 1, 1, false), 'deny');
expect(Policy::editAction(1, 1, 1, true), 'edit-original');
expect(Policy::editAction(2, 2, 1, false), 'edit-owned');
expect(Policy::editAction(3, 2, 1, false), 'deny');
expect(Policy::editAction(0, 2, 1, false), 'deny');
echo "Workspace ownership and default feature policy checks passed.\n";
