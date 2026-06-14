<?php

namespace Database\Seeders;

use App\Models\LegalResearchEntry;
use Illuminate\Database\Seeder;

class LegalResearchEntrySeeder extends Seeder
{
    public function run(): void
    {
        $entries = [
            [
                'title' => 'Celotex Corp. v. Catrett',
                'citation' => '477 U.S. 317 (1986)',
                'summary' => 'Established the burden-shifting framework for summary judgment in federal court.',
                'jurisdiction' => 'U.S. Supreme Court',
                'document_type' => 'case',
                'tags' => ['summary judgment', 'burden of proof', 'civil procedure'],
            ],
            [
                'title' => 'Federal Rule of Civil Procedure 56',
                'citation' => 'Fed. R. Civ. P. 56',
                'summary' => 'Governs summary judgment motions in federal district courts.',
                'jurisdiction' => 'Federal',
                'document_type' => 'regulation',
                'tags' => ['summary judgment', 'federal rules'],
            ],
            [
                'title' => 'Restatement (Second) of Contracts § 90',
                'citation' => 'Restatement (Second) of Contracts § 90',
                'summary' => 'Promissory estoppel doctrine when a promise induces reasonable reliance.',
                'jurisdiction' => 'General',
                'document_type' => 'principle',
                'tags' => ['contracts', 'promissory estoppel', 'reliance'],
            ],
            [
                'title' => 'California Civil Code § 1549',
                'citation' => 'Cal. Civ. Code § 1549',
                'summary' => 'Defines a contract as an agreement creating obligations enforceable by law.',
                'jurisdiction' => 'California',
                'document_type' => 'statute',
                'tags' => ['contracts', 'california'],
            ],
            [
                'title' => 'Motion to Dismiss — Failure to State a Claim',
                'citation' => '12(b)(6) Research Note',
                'summary' => 'Internal note on pleading standards under Rule 12(b)(6) and plausible claim requirements.',
                'jurisdiction' => 'Federal',
                'document_type' => 'note',
                'tags' => ['motion to dismiss', 'pleading standards'],
            ],
        ];

        foreach ($entries as $entry) {
            LegalResearchEntry::query()->firstOrCreate(
                [
                    'organization_id' => null,
                    'title' => $entry['title'],
                ],
                $entry,
            );
        }
    }
}
