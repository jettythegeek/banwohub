<?php

namespace Database\Seeders;

use App\Models\KnowledgeArticle;
use Illuminate\Database\Seeder;

class KnowledgeArticleSeeder extends Seeder
{
    public function run(): void
    {
        $articles = [
            [
                'title' => 'Client Intake Checklist',
                'excerpt' => 'Standard steps for opening a new civil litigation matter.',
                'content' => "1. Run conflict check\n2. Collect retainer agreement\n3. Open matter in Banwolaw Hub\n4. Assign lead lawyer and paralegal",
                'content_type' => 'sop',
                'category' => 'sops',
                'practice_area' => 'Civil litigation',
                'tags' => ['intake', 'onboarding', 'checklist'],
            ],
            [
                'title' => 'Limitation of Liability Clause',
                'excerpt' => 'Standard commercial contract limitation clause.',
                'content' => 'IN NO EVENT SHALL EITHER PARTY BE LIABLE FOR ANY INDIRECT, INCIDENTAL, SPECIAL, CONSEQUENTIAL, OR PUNITIVE DAMAGES ARISING OUT OF OR RELATED TO THIS AGREEMENT.',
                'content_type' => 'clause_snippet',
                'category' => 'clauses',
                'practice_area' => 'Commercial contracts',
                'tags' => ['contracts', 'liability', 'boilerplate'],
            ],
            [
                'title' => 'Summary Judgment Motion Practice Guide',
                'excerpt' => 'Firm workflow for drafting and filing summary judgment motions.',
                'content' => 'Review Celotex burden-shifting framework. Confirm record evidence. Draft statement of undisputed facts. Route through partner review before filing.',
                'content_type' => 'practice_guide',
                'category' => 'practice_guides',
                'practice_area' => 'Civil litigation',
                'tags' => ['summary judgment', 'motions', 'federal'],
            ],
            [
                'title' => 'Data Retention Policy',
                'excerpt' => 'Internal policy for matter file retention and destruction.',
                'content' => 'Closed matters are retained for seven years unless litigation hold applies. Electronic files follow the same schedule with secure destruction.',
                'content_type' => 'policy',
                'category' => 'internal_policies',
                'practice_area' => 'Firm operations',
                'tags' => ['records', 'compliance', 'retention'],
            ],
            [
                'title' => 'New Associate Onboarding — Litigation Track',
                'excerpt' => 'Training outline for litigation associates during first 90 days.',
                'content' => 'Week 1: systems access and ethics training. Weeks 2–4: shadow depositions and motion practice. Month 2–3: supervised drafting assignments.',
                'content_type' => 'training',
                'category' => 'training',
                'practice_area' => 'Professional development',
                'tags' => ['onboarding', 'training', 'litigation'],
            ],
        ];

        foreach ($articles as $article) {
            KnowledgeArticle::query()->firstOrCreate(
                [
                    'organization_id' => null,
                    'title' => $article['title'],
                ],
                $article,
            );
        }
    }
}
