<?php
/**
 * Sensei LMS Course Seeder
 *
 * Creates a fully-structured Sensei LMS course with modules, lessons and quizzes
 * using a fluent builder API:
 *
 *   SenseiCourseSeeder::create()->seed_course()->with_module(3)->lesson(2)->quiz(3);
 *
 * - with_module(N)  → N modules (Sensei 'module' taxonomy terms)
 * - lesson(N)       → N lessons per module
 * - quiz(N)         → one quiz per lesson, each quiz has N questions
 *
 * @package TutorLMSMigrationTool
 * @since   2.7.0
 */

namespace Themeum\TutorLMSMigrationTool\Seeders;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fluent builder that seeds a Sensei LMS course with realistic dummy data.
 */
class SenseiCourseSeeder {

	/**
	 * WordPress user ID to assign as post author.
	 *
	 * @var int
	 */
	private int $author_id;

	/**
	 * Course title. Generated automatically when empty.
	 *
	 * @var string
	 */
	private string $course_title = '';

	/**
	 * Number of modules to create.
	 *
	 * @var int
	 */
	private int $module_count = 1;

	/**
	 * Number of lessons per module.
	 *
	 * @var int
	 */
	private int $lesson_count = 1;

	/**
	 * Number of questions per quiz (one quiz per lesson).
	 *
	 * @var int
	 */
	private int $question_count = 3;

	/**
	 * Passing grade percentage applied to every quiz.
	 *
	 * @var int
	 */
	private int $passing_grade = 70;

	/**
	 * Whether students can retake the quiz.
	 *
	 * @var bool
	 */
	private bool $allow_retakes = true;

	/**
	 * Whether correct answers are revealed after submission.
	 *
	 * @var bool
	 */
	private bool $show_answers = true;

	// ── Course topics used to generate realistic content ──────────────────────

	/**
	 * Pool of course topics. One is picked randomly.
	 *
	 * @var array<string, array{modules: string[], lesson_prefix: string, description: string}>
	 */
	private static array $topics = array(
		'Python Programming'      => array(
			'description'   => 'A beginner-friendly course covering Python fundamentals including variables, control flow, functions and data structures.',
			'modules'       => array( 'Getting Started', 'Control Flow', 'Functions and Scope', 'Data Structures', 'File Handling', 'Object-Oriented Python' ),
			'lesson_prefix' => 'Understanding',
			'lesson_topics' => array(
				'Variables and Types',
				'Strings',
				'Numbers',
				'Booleans',
				'Lists',
				'Tuples',
				'Dictionaries',
				'Sets',
				'Conditionals',
				'Loops',
				'Functions',
				'Classes',
				'Modules',
				'File I/O',
				'Exception Handling',
				'List Comprehensions',
			),
		),
		'Web Design Fundamentals' => array(
			'description'   => 'Learn modern web design principles covering HTML, CSS layouts, typography and responsive design.',
			'modules'       => array( 'HTML Foundations', 'CSS Basics', 'Layouts and Grid', 'Typography', 'Responsive Design', 'UI Components' ),
			'lesson_prefix' => 'Working with',
			'lesson_topics' => array(
				'HTML Structure',
				'Semantic Elements',
				'Forms',
				'Links and Images',
				'CSS Selectors',
				'Box Model',
				'Flexbox',
				'CSS Grid',
				'Media Queries',
				'Variables',
				'Animations',
				'Pseudo-classes',
				'Typography Scale',
				'Color Theory',
				'Spacing',
				'Accessibility',
			),
		),
		'Digital Marketing'       => array(
			'description'   => 'A practical guide to digital marketing covering SEO, content strategy, social media and analytics.',
			'modules'       => array( 'Marketing Foundations', 'SEO', 'Content Marketing', 'Social Media', 'Email Marketing', 'Analytics' ),
			'lesson_prefix' => 'Mastering',
			'lesson_topics' => array(
				'Target Audiences',
				'Brand Voice',
				'Keyword Research',
				'On-Page SEO',
				'Link Building',
				'Content Planning',
				'Blog Writing',
				'Video Content',
				'Instagram Strategy',
				'Facebook Ads',
				'Email Funnels',
				'A/B Testing',
				'Google Analytics',
				'Conversion Rates',
				'Reporting',
				'Growth Hacking',
			),
		),
		'Project Management'      => array(
			'description'   => 'Learn how to plan, execute and deliver projects on time using proven frameworks and tools.',
			'modules'       => array( 'PM Fundamentals', 'Planning', 'Agile Methods', 'Risk Management', 'Team Leadership', 'Delivery and Closure' ),
			'lesson_prefix' => 'Applying',
			'lesson_topics' => array(
				'Project Lifecycle',
				'Stakeholders',
				'Scope Definition',
				'Work Breakdown',
				'Gantt Charts',
				'Resource Planning',
				'Scrum',
				'Kanban',
				'Sprints',
				'Risk Registers',
				'Issue Tracking',
				'Communication Plans',
				'Team Motivation',
				'Conflict Resolution',
				'Quality Gates',
				'Retrospectives',
			),
		),
	);

	/**
	 * Constructor.
	 *
	 * @param int $author_id WordPress user ID for post authorship. Defaults to 1 (admin).
	 */
	public function __construct( int $author_id = 1 ) {
		$this->author_id = $author_id > 0 ? $author_id : 1;
	}

	/**
	 * Static factory — allows starting the fluent chain without `new`.
	 *
	 * @param int $author_id WordPress user ID. Defaults to 1.
	 * @return static
	 */
	public static function create( int $author_id = 1 ): static {
		return new static( $author_id );
	}

	/**
	 * Set the course title and begin the builder chain.
	 *
	 * @param string $title Course title. Leave empty to auto-generate from a random topic.
	 * @return static
	 */
	public function seed_course( string $title = '' ): static {
		$this->course_title = $title;
		return $this;
	}

	/**
	 * Set the number of modules to create.
	 *
	 * @param int $count Number of modules (minimum 1).
	 * @return static
	 */
	public function with_module( int $count ): static {
		$this->module_count = max( 1, $count );
		return $this;
	}

	/**
	 * Set the number of lessons per module.
	 *
	 * @param int $count Lessons per module (minimum 1).
	 * @return static
	 */
	public function lesson( int $count ): static {
		$this->lesson_count = max( 1, $count );
		return $this;
	}

	/**
	 * Set the number of questions per quiz, then run the seeder.
	 *
	 * Each lesson receives one quiz. The quiz will have $questions_per_quiz questions,
	 * alternating between multiple-choice and true/false types.
	 *
	 * @param int $questions_per_quiz Questions in each lesson quiz (minimum 1).
	 * @return array{
	 *   course_id: int,
	 *   modules: array<int, array{term_id: int, name: string, lessons: array}>,
	 *   summary: array{modules: int, lessons: int, quizzes: int, questions: int}
	 * }
	 */
	public function quiz( int $questions_per_quiz ): array {
		$this->question_count = max( 1, $questions_per_quiz );
		return $this->run();
	}

	/**
	 * Configure passing grade for all quizzes.
	 *
	 * @param int $percent Passing grade percentage (0–100).
	 * @return static
	 */
	public function passing_grade( int $percent ): static {
		$this->passing_grade = max( 0, min( 100, $percent ) );
		return $this;
	}

	/**
	 * Allow or disallow quiz retakes.
	 *
	 * @param bool $allow True to allow multiple attempts.
	 * @return static
	 */
	public function allow_retakes( bool $allow = true ): static {
		$this->allow_retakes = $allow;
		return $this;
	}

	/**
	 * Show or hide correct answers after quiz submission.
	 *
	 * @param bool $show True to reveal correct answers.
	 * @return static
	 */
	public function show_answers( bool $show = true ): static {
		$this->show_answers = $show;
		return $this;
	}

	// ── Internal execution ────────────────────────────────────────────────────

	/**
	 * Execute the seed: create course, modules, lessons and quizzes.
	 *
	 * @return array
	 */
	private function run(): array {
		$topic_key    = array_rand( static::$topics );
		$topic        = static::$topics[ $topic_key ];
		$course_title = $this->course_title ?: $topic_key . ' — Seed Course ' . wp_generate_password( 4, false );

		// ── Course ────────────────────────────────────────────────────────────
		$course_id = wp_insert_post(
			array(
				'post_type'    => 'course',
				'post_title'   => $course_title,
				'post_content' => $topic['description'],
				'post_status'  => 'publish',
				'post_author'  => $this->author_id,
			)
		);

		if ( is_wp_error( $course_id ) || ! $course_id ) {
			return array( 'error' => 'Failed to create course.' );
		}

		$module_names  = $topic['modules'];
		$lesson_topics = $topic['lesson_topics'];
		$module_order  = array();
		$result        = array(
			'course_id' => $course_id,
			'modules'   => array(),
			'summary'   => array(
				'modules'   => 0,
				'lessons'   => 0,
				'quizzes'   => 0,
				'questions' => 0,
			),
		);

		$lesson_topic_index = 0;

		// ── Modules ───────────────────────────────────────────────────────────
		for ( $m = 0; $m < $this->module_count; $m++ ) {
			$module_name = $module_names[ $m % count( $module_names ) ];
			if ( $m >= count( $module_names ) ) {
				$module_name .= ' ' . ( intdiv( $m, count( $module_names ) ) + 1 );
			}

			$term = wp_insert_term(
				$module_name . ' (' . $course_title . ')',
				'module',
				array( 'description' => 'Module covering ' . strtolower( $module_name ) . '.' )
			);

			if ( is_wp_error( $term ) ) {
				continue;
			}

			$module_term_id = (int) $term['term_id'];
			$module_order[] = $module_term_id;

			wp_set_post_terms( $course_id, array( $module_term_id ), 'module', true );

			$module_result = array(
				'term_id' => $module_term_id,
				'name'    => $module_name,
				'lessons' => array(),
			);

			++$result['summary']['modules'];

			// ── Lessons ───────────────────────────────────────────────────────
			for ( $l = 0; $l < $this->lesson_count; $l++ ) {
				$lesson_topic = $lesson_topics[ $lesson_topic_index % count( $lesson_topics ) ];
				++$lesson_topic_index;

				$lesson_title   = $topic['lesson_prefix'] . ' ' . $lesson_topic;
				$lesson_content = 'In this lesson you will learn about ' . strtolower( $lesson_topic ) . '. '
					. 'By the end you will be able to apply this knowledge confidently in real projects.';

				$lesson_id = wp_insert_post(
					array(
						'post_type'    => 'lesson',
						'post_title'   => $lesson_title,
						'post_content' => $lesson_content,
						'post_status'  => 'publish',
						'post_author'  => $this->author_id,
						'menu_order'   => $l,
					)
				);

				if ( is_wp_error( $lesson_id ) || ! $lesson_id ) {
					continue;
				}

				update_post_meta( $lesson_id, '_lesson_course', $course_id );
				update_post_meta( $lesson_id, '_order_module_' . $module_term_id, $l );
				wp_set_post_terms( $lesson_id, array( $module_term_id ), 'module', false );

				++$result['summary']['lessons'];

				// ── Quiz ──────────────────────────────────────────────────────
				$quiz_id = $this->create_quiz( $lesson_id, $course_id, $lesson_title, $lesson_topic );

				$module_result['lessons'][] = array(
					'lesson_id' => $lesson_id,
					'title'     => $lesson_title,
					'quiz_id'   => $quiz_id,
				);

				if ( $quiz_id ) {
					++$result['summary']['quizzes'];
					$result['summary']['questions'] += $this->question_count;
				}
			}

			$result['modules'][] = $module_result;
		}

		update_post_meta( $course_id, '_module_order', $module_order );

		return $result;
	}

	/**
	 * Create a quiz with questions for a lesson.
	 *
	 * @param int    $lesson_id    Lesson post ID.
	 * @param int    $course_id    Course post ID.
	 * @param string $lesson_title Lesson title used to name the quiz.
	 * @param string $topic        Topic slug used to generate question text.
	 * @return int Quiz post ID, or 0 on failure.
	 */
	private function create_quiz( int $lesson_id, int $course_id, string $lesson_title, string $topic ): int {
		$quiz_id = wp_insert_post(
			array(
				'post_type'   => 'quiz',
				'post_title'  => $lesson_title . ' Quiz',
				'post_status' => 'publish',
				'post_author' => $this->author_id,
			)
		);

		if ( is_wp_error( $quiz_id ) || ! $quiz_id ) {
			return 0;
		}

		update_post_meta( $quiz_id, '_quiz_lesson', $lesson_id );
		update_post_meta( $quiz_id, '_quiz_course', $course_id );
		update_post_meta( $quiz_id, '_quiz_passmark', $this->passing_grade );
		update_post_meta( $quiz_id, '_enable_quiz_reset', $this->allow_retakes ? 'yes' : '' );
		update_post_meta( $quiz_id, '_random_question_order', '' );
		update_post_meta( $quiz_id, '_failed_show_correct_answers', $this->show_answers ? 'yes' : '' );

		update_post_meta( $lesson_id, '_lesson_quiz', $quiz_id );

		$this->create_questions( $quiz_id, $topic );

		return $quiz_id;
	}

	/**
	 * Create questions for a quiz.
	 *
	 * Questions alternate between multiple-choice and true/false. Content is
	 * generated from the lesson topic so it reads naturally.
	 *
	 * @param int    $quiz_id Quiz post ID.
	 * @param string $topic   Topic label used to build question text.
	 * @return void
	 */
	private function create_questions( int $quiz_id, string $topic ): void {
		$mc_templates = $this->multiple_choice_templates( $topic );
		$tf_templates = $this->true_false_templates( $topic );

		$mc_index = 0;
		$tf_index = 0;

		for ( $i = 0; $i < $this->question_count; $i++ ) {
			// Alternate: even index → multiple-choice, odd → true/false.
			if ( $i % 2 === 0 ) {
				$tpl  = $mc_templates[ $mc_index % count( $mc_templates ) ];
				$type = 'multiple-choice';
				++$mc_index;
			} else {
				$tpl  = $tf_templates[ $tf_index % count( $tf_templates ) ];
				$type = 'boolean';
				++$tf_index;
			}

			$question_id = wp_insert_post(
				array(
					'post_type'   => 'question',
					'post_title'  => $tpl['question'],
					'post_status' => 'publish',
					'post_author' => $this->author_id,
					'menu_order'  => $i,
				)
			);

			if ( is_wp_error( $question_id ) || ! $question_id ) {
				continue;
			}

			wp_set_post_terms( $question_id, array( $type ), 'question-type', false );

			update_post_meta( $question_id, '_quiz_id', $quiz_id );
			update_post_meta( $question_id, '_question_grade', 1 );
			update_post_meta( $question_id, '_question_right_answer', $tpl['correct'] );
			update_post_meta( $question_id, '_quiz_question_order' . $quiz_id, $i );

			if ( 'multiple-choice' === $type && ! empty( $tpl['wrong'] ) ) {
				update_post_meta( $question_id, '_question_wrong_answers', $tpl['wrong'] );
			}
		}
	}

	/**
	 * Generate multiple-choice question templates for a topic.
	 *
	 * @param string $topic Lesson topic label.
	 * @return array<int, array{question: string, correct: string, wrong: string[]}>
	 */
	private function multiple_choice_templates( string $topic ): array {
		$t = $topic;
		return array(
			array(
				'question' => "Which of the following best describes {$t}?",
				'correct'  => "A structured approach to understanding {$t}",
				'wrong'    => array( "A random process unrelated to {$t}", "An outdated concept replaced by {$t}", 'None of the above' ),
			),
			array(
				'question' => "What is the primary purpose of {$t}?",
				'correct'  => "To organise and manage {$t} effectively",
				'wrong'    => array( 'To replace all other methods', 'To complicate the learning process', "To avoid using {$t} altogether" ),
			),
			array(
				'question' => "Which statement about {$t} is correct?",
				'correct'  => "{$t} follows a well-defined set of principles",
				'wrong'    => array( "{$t} has no practical applications", "{$t} is only used in advanced scenarios", "{$t} cannot be learned online" ),
			),
			array(
				'question' => "When working with {$t}, which approach is recommended?",
				'correct'  => 'Start with the fundamentals before advancing',
				'wrong'    => array( 'Skip the basics and go straight to advanced topics', 'Memorise everything without understanding', 'Avoid practising with examples' ),
			),
		);
	}

	/**
	 * Generate true/false question templates for a topic.
	 *
	 * @param string $topic Lesson topic label.
	 * @return array<int, array{question: string, correct: string}>
	 */
	private function true_false_templates( string $topic ): array {
		$t = $topic;
		return array(
			array(
				'question' => "{$t} is a fundamental concept in this course.",
				'correct'  => 'true',
			),
			array(
				'question' => "Understanding {$t} requires no prior knowledge.",
				'correct'  => 'false',
			),
			array(
				'question' => "{$t} can be applied in real-world projects.",
				'correct'  => 'true',
			),
			array(
				'question' => "{$t} is considered an advanced topic only.",
				'correct'  => 'false',
			),
		);
	}
}
