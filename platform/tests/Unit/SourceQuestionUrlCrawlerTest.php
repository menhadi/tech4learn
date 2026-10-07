<?php

namespace Tests\Unit;

use App\Services\SourceQuestionUrlCrawler;
use PHPUnit\Framework\TestCase;

class SourceQuestionUrlCrawlerTest extends TestCase
{
    public function test_it_discovers_examside_questions_and_only_crawls_same_scope_pages(): void
    {
        $result = (new SourceQuestionUrlCrawler)->parse(<<<'HTML'
<a href="question/physics-AAAAAAAAAAAAAAAA.htm">Question one</a>
<a href="https://questions.examside.com/past-years/jee/question/math-BBBBBBBBBBBBBBBB.htm#answer">Question two</a>
<a href="page-2.htm">Next page</a>
<a href="?page=3">Query pagination</a>
<a href="questions.pdf">Linked document</a>
<a href="/unrelated/page.htm">Outside base path</a>
<a href="https://example.com/past-years/jee/page-3.htm">External</a>
HTML,
            'https://questions.examside.com/past-years/jee/index.htm',
            'https://questions.examside.com/past-years/jee/index.htm',
            'examside',
        );

        $this->assertSame([
            'https://questions.examside.com/past-years/jee/question/physics-AAAAAAAAAAAAAAAA.htm',
            'https://questions.examside.com/past-years/jee/question/math-BBBBBBBBBBBBBBBB.htm',
        ], $result['question_urls']);
        $this->assertSame([
            'https://questions.examside.com/past-years/jee/page-2.htm',
            'https://questions.examside.com/past-years/jee/index.htm?page=3',
        ], $result['crawl_urls']);
    }

    public function test_it_uses_an_admin_question_url_wildcard_for_other_adapters(): void
    {
        $result = (new SourceQuestionUrlCrawler)->parse(
            '<a href="/bank/question/42">Question</a><a href="/bank/page/2">Next</a>',
            'https://school.example/bank/index',
            'https://school.example/bank/index',
            'school',
            '*://school.example/bank/question/*',
        );

        $this->assertSame(['https://school.example/bank/question/42'], $result['question_urls']);
        $this->assertSame(['https://school.example/bank/page/2'], $result['crawl_urls']);
    }
}
