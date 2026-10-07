<?php
require __DIR__.'/native-student-review.php';
$valid=['settings'=>'{"is_primary_platform":false}','organisation_status'=>'active','student_status'=>'Active'];
if (!nativeAttendanceStudentReady([$valid])) throw new RuntimeException('Active tenant student rejected');
foreach (['settings'=>'{"is_primary_platform":true}','organisation_status'=>'inactive','student_status'=>'Pending'] as $key=>$value) {
    $invalid=$valid; $invalid[$key]=$value;
    if (nativeAttendanceStudentReady([$invalid])) throw new RuntimeException('Inactive identity accepted');
    unset($invalid[$key]);
    if (nativeAttendanceStudentReady([$invalid])) throw new RuntimeException('Missing authority accepted');
}
foreach ([[],[$valid,$valid],[array_replace($valid,['student_status'=>'Suspend'])],[array_replace($valid,['settings'=>'invalid'])],[array_replace($valid,['settings'=>'{}'])]] as $rows)
    if (nativeAttendanceStudentReady($rows)) throw new RuntimeException('Ambiguous identity accepted');
echo "PASS: native student active-state, tenant realm and ambiguity boundaries\n";
$db = new PDO('sqlite::memory:');
$db->exec("CREATE TABLE organizations(id INTEGER,status TEXT,settings TEXT); CREATE TABLE students(id INTEGER,organization_id INTEGER,status TEXT);");
$db->exec(<<<'SQL'
INSERT INTO organizations VALUES(1,'active','{"is_primary_platform":false}'),(2,'active','{"is_primary_platform":false}'); INSERT INTO students VALUES(10,2,'Active');
SQL
);
$source = file_get_contents(__DIR__.'/check-native-attendance-student.php');
if (!preg_match("/prepare\('([^']+)'\)/", $source, $match)) throw new RuntimeException('Review query missing');
$query = $db->prepare($match[1]);
$query->execute([1,10]);
if (nativeAttendanceStudentReady($query->fetchAll(PDO::FETCH_ASSOC))) throw new RuntimeException('Foreign student accepted');
$query->execute([2,10]);
if (!nativeAttendanceStudentReady($query->fetchAll(PDO::FETCH_ASSOC))) throw new RuntimeException('Owned student rejected');
echo "PASS: actual review query rejects foreign organisation ownership\n";
