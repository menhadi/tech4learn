<?php

namespace Tests\Unit;

use App\Models\Exam;
use App\Models\Stopic;
use App\Models\Subject;
use App\Models\Topic;
use App\Services\ExamWorkbookService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class ExamWorkbookExportClassificationTest extends TestCase
{
    public function test_export_row_round_trips_test_type_and_hierarchy_ids_with_readable_names(): void
    {
        $exam = new Exam([
            'id' => 42,
            'test_type' => Exam::TEST_TYPE_SUBTOPIC,
            'test_subject_id' => 7,
            'test_topic_id' => 8,
            'test_stopic_id' => 9,
        ]);
        $exam->setRelation('testSubject', new Subject(['subject_name' => 'Physics']));
        $exam->setRelation('testTopic', new Topic(['name' => 'Mechanics']));
        $exam->setRelation('testSubtopic', new Stopic(['name' => 'Kinematics']));
        $exam->setRelation('groups', new Collection());
        $exam->setRelation('packages', new Collection());
        $exam->setRelation('qualitySources', new Collection());
        $exam->setRelation('category', null);
        $exam->setRelation('subcategory', null);

        $service = new ExamWorkbookService();
        $row = array_combine($service->headings(), $service->exportRow($exam));

        $this->assertSame(Exam::TEST_TYPE_SUBTOPIC, $row['test_type']);
        $this->assertSame('Subtopic Tests', $row['test_type_label']);
        $this->assertSame(7, $row['test_subject_id']);
        $this->assertSame('Physics', $row['test_subject_name']);
        $this->assertSame(8, $row['test_topic_id']);
        $this->assertSame('Mechanics', $row['test_topic_name']);
        $this->assertSame(9, $row['test_subtopic_id']);
        $this->assertSame('Kinematics', $row['test_subtopic_name']);
    }
}
