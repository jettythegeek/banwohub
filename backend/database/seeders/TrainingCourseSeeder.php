<?php

namespace Database\Seeders;

use App\Models\TrainingCourse;
use Illuminate\Database\Seeder;

class TrainingCourseSeeder extends Seeder
{
    public function run(): void
    {
        $courses = [
            [
                'title' => 'Ethics in Client Communication',
                'description' => 'Professional responsibility when communicating with clients and opposing counsel.',
                'content' => 'Covers confidentiality, candor, and written communication best practices.',
                'cle_credits' => 1.0,
                'is_required' => true,
                'quiz_questions' => [
                    [
                        'question' => 'When may you disclose client information without consent?',
                        'options' => [
                            'Whenever convenient',
                            'Only when permitted by professional rules',
                            'To any staff member',
                            'On social media with names redacted',
                        ],
                        'correct_index' => 1,
                    ],
                    [
                        'question' => 'What should you do before sending a substantive client email?',
                        'options' => [
                            'Send immediately',
                            'Review for accuracy and privilege',
                            'CC opposing counsel',
                            'Post to the firm blog',
                        ],
                        'correct_index' => 1,
                    ],
                ],
            ],
            [
                'title' => 'Data Security for Law Firms',
                'description' => 'Protecting client data, matter files, and firm systems.',
                'content' => 'Password hygiene, phishing awareness, and secure document handling.',
                'cle_credits' => 0.5,
                'is_required' => true,
                'quiz_questions' => [
                    [
                        'question' => 'Which is the best practice for matter file access?',
                        'options' => [
                            'Share one login for the team',
                            'Use role-based access and MFA',
                            'Email passwords in plain text',
                            'Store files on personal USB drives',
                        ],
                        'correct_index' => 1,
                    ],
                ],
            ],
            [
                'title' => 'Civil Discovery Fundamentals',
                'description' => 'Introductory CLE on discovery planning and document production.',
                'content' => 'Meet-and-confer, preservation, and proportionality concepts.',
                'cle_credits' => 2.0,
                'is_required' => false,
                'quiz_questions' => [
                    [
                        'question' => 'What is a litigation hold?',
                        'options' => [
                            'A court order to dismiss',
                            'A directive to preserve relevant information',
                            'A fee agreement',
                            'A jury instruction',
                        ],
                        'correct_index' => 1,
                    ],
                ],
            ],
        ];

        foreach ($courses as $course) {
            TrainingCourse::query()->updateOrCreate(
                [
                    'organization_id' => null,
                    'title' => $course['title'],
                ],
                [
                    ...$course,
                    'is_published' => true,
                    'passing_score' => 70,
                ]
            );
        }
    }
}
