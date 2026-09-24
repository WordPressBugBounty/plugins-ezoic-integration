<?php

namespace Ezoic_Namespace;

/**
 * Parent Filter Definition
 */
class Ezoic_AdTester_Parent_Filter
{
	// Parses a selector (e.g. div#my-id.foo.bar)
	const FILTER_PARSER = '/^([\*\w\-]+)?(#[\w\-]+)?((?:\.[\w\-]+)*)$/i';

	public $tag;
	public $id;
	public $classes;

	public function __construct() {}

	/**
	 * Parses a filter string and returns an Ezoic_AdTester_Parent_Filter, or null when
	 * the selector is not a plain tag#id.class form. An empty filter matches every
	 * parent, so an unparsed selector must never become one.
	 */
	public static function parse_filter($filter)
	{
		// Parse filter string
		if (!\is_string($filter) || !preg_match_all(Ezoic_AdTester_Parent_Filter::FILTER_PARSER, \trim($filter), $parsed)) {
			return null;
		}

		// The new filter
		$new_filter = new Ezoic_AdTester_Parent_Filter();

		// Tag/Element
		if (!empty($parsed[1][0])) {
			$new_filter->tag = \ez_strtolower($parsed[1][0]);
		}

		// Id
		if (!empty($parsed[2][0])) {
			$new_filter->id = \ez_strtolower(\ez_substr($parsed[2][0], 1));
		}

		// Classes: split ".foo.bar" into every non-empty token
		if (!empty($parsed[3][0])) {
			$class_tokens = array();
			foreach (\explode('.', $parsed[3][0]) as $token) {
				if ($token !== '') {
					$class_tokens[] = \ez_strtolower($token);
				}
			}
			if (!empty($class_tokens)) {
				$new_filter->classes = $class_tokens;
			}
		}

		if (!isset($new_filter->tag) && !isset($new_filter->id) && !isset($new_filter->classes)) {
			return null;
		}

		return $new_filter;
	}

	/**
	 * Indicates if the filter matches the current paragraph.
	 * Matching is case-insensitive: parse_filter() lowercases the filter. The tag
	 * parser already lowercases tag names but keeps id/class values as written, so
	 * the element's id/class are lowercased here. When the filter lists multiple
	 * classes, every required class must be present on the element.
	 */
	public function is_valid($paragraph)
	{
		foreach ($paragraph->lineage as $parent_element) {
			// Evaluate filter
			$tag_match		= !isset($this->tag)		|| (\array_key_exists('tag', $parent_element) && $this->tag === $parent_element['tag']);
			$id_match		= !isset($this->id)		|| (\array_key_exists('id', $parent_element) && $this->id === \ez_strtolower($parent_element['id']));
			$class_match	= !isset($this->classes);
			if (!$class_match && \array_key_exists('class_list', $parent_element)) {
				$element_classes = \array_map('\ez_strtolower', $parent_element['class_list']);
				$class_match = true;
				foreach ($this->classes as $required_class) {
					if (!\in_array($required_class, $element_classes, true)) {
						$class_match = false;
						break;
					}
				}
			}

			// Return true if all matches are true
			if ($tag_match && $id_match && $class_match) {
				return true;
			}
		}

		return false;
	}
}

class Ezoic_AdTester_Content_Inserter2 extends Ezoic_AdTester_Inserter
{
	private $position_offset = 0;
	private $paragraphs;
	private $paragraph_limit_warning_shown = false;

	public function __construct($config)
	{
		parent::__construct($config);
	}

	/**
	 * Insert placeholders into content
	 */
	public function insert($content)
	{
		// Reset paragraph limit warning flag for new content processing
		$this->paragraph_limit_warning_shown = false;

		// Validation
		if (!isset($content) || \ez_strlen($content) === 0) {
			Ezoic_Integration_Logger::console_debug(
				"Content insertion skipped - content is empty or null",
				'Content Ads',
				'info'
			);
			return $content;
		}

		// Find rules that apply to this page
		$rules = $this->config->filtered_placeholder_rules;

		// Stop processing if there are no rules to process for this page
		if (\count($rules) === 0) {
			return $content;
		}

		// Check if any placements have been marked as inserted
		// If so, check if the content already has placeholders - if not, we need to re-insert
		$any_marked_inserted = false;
		foreach ($rules as $rule) {
			if ($rule->display != 'disabled') {
				if (!isset($this->config->placeholders[$rule->placeholder_id])) {
					Ezoic_Integration_Logger::console_debug(
						"Rule skipped - placeholder_id '{$rule->placeholder_id}' not found in config.",
						'Content Ads',
						'warn'
					);
					continue;
				}
				$placeholder = $this->config->placeholders[$rule->placeholder_id];
				if (Ezoic_AdTester::is_placement_inserted($placeholder->position_id)) {
					$any_marked_inserted = true;
					break;
				}
			}
		}

		// If placements are marked as inserted but content doesn't have them, the_content was called again with original content
		// In this case, we need to re-process to add the ads back
		if ($any_marked_inserted) {
			// Check if the specific marked placements exist in content
			$all_marked_placements_present = true;
			foreach ($rules as $rule) {
				if ($rule->display != 'disabled') {
					if (!isset($this->config->placeholders[$rule->placeholder_id])) {
						Ezoic_Integration_Logger::console_debug(
							"Rule skipped - placeholder_id '{$rule->placeholder_id}' not found in config.",
							'Content Ads',
							'warn'
						);
						continue;
					}
					$placeholder = $this->config->placeholders[$rule->placeholder_id];
					if (Ezoic_AdTester::is_placement_inserted($placeholder->position_id)) {
						// This placement is marked as inserted, verify it exists in content
						if (\strpos($content, "ezoic-pub-ad-placeholder-{$placeholder->position_id}") === false) {
							// Placement marked as inserted but not in content
							$all_marked_placements_present = false;
						}
					}
				}
			}

			if ($all_marked_placements_present) {
				// All marked placements are present in content, return as-is
				return $content;
			}
		}

		// Sort rules based on paragraph order
		\usort($rules, function ($a, $b) {
			$a_pos = (int) $a->display_option;
			$b_pos = (int) $b->display_option;

			if ($a_pos !== $b_pos) {
				return $a_pos < $b_pos ? -1 : 1;
			}

			// For the same paragraph, insert "before" first to avoid offset collisions
			$a_before = ($a->display === 'before_paragraph');
			$b_before = ($b->display === 'before_paragraph');
			if ($a_before !== $b_before) {
				return $a_before ? -1 : 1;
			}

			return 0;
		});

		// Extract all paragraph tags
		$this->paragraphs = $this->get_paragraphs($content);

		// If no paragraphs found, skip silently (likely processing non-content areas like widgets)
		if (\count($this->paragraphs) === 0) {
			return $content;
		}

		// Used to tell "different post, same page" (skip - avoid a duplicate id)
		// apart from "same post, the_content called again with fresh content"
		// (fall through and re-insert) in the guard below.
		$current_post_id = \function_exists('get_the_ID') ? \get_the_ID() : false;

		// Insert placeholders
		foreach ($rules as $rule) {
			if (!isset($this->config->placeholders[$rule->placeholder_id])) {
				Ezoic_Integration_Logger::console_debug(
					"Rule skipped - placeholder_id '{$rule->placeholder_id}' not found in config.",
					'Content Ads',
					'warn'
				);
				continue;
			}
			$placeholder = $this->config->placeholders[$rule->placeholder_id];

			if ($rule->display != 'disabled') {
				// Skip if this placement was already inserted on this page for a
				// DIFFERENT post. If it was inserted for the SAME post, this is the
				// pre-loop re-insertion path above re-processing fresh content (a
				// theme calling the_content twice on the same post) - fall through.
				if (
					Ezoic_AdTester::is_placement_inserted($placeholder->position_id)
					&& Ezoic_AdTester::get_placement_inserted_post_id($placeholder->position_id) !== $current_post_id
				) {
					Ezoic_Integration_Logger::console_debug(
						"Placement skipped - already inserted on this page.",
						'Content Ads',
						'info',
						$placeholder->position_id
					);
					continue;
				}

				// Skip if this placeholder already exists in content
				if (strpos($content, "ezoic-pub-ad-placeholder-{$placeholder->position_id}") !== false) {
					Ezoic_Integration_Logger::console_debug(
						"Placement skipped - placeholder already exists in content.",
						'Content Ads',
						'info',
						$placeholder->position_id
					);
					continue;
				}

				switch ($rule->display) {
					case 'before_paragraph':
						$content = $this->relative_to_paragraph($placeholder, $rule->display_option, $content, 'before', $current_post_id);
						break;

					case 'after_paragraph':
						$content = $this->relative_to_paragraph($placeholder, $rule->display_option, $content, 'after', $current_post_id);
						break;

					default:
						continue 2;
				}
			} else {
				Ezoic_Integration_Logger::console_debug(
					"Placement skipped - display is disabled.",
					'Content Ads',
					'info',
					$placeholder->position_id
				);
			}
		}

		return $content;
	}

	/**
	 * Inserts a placeholder either before or after a paragraph
	 */
	private function relative_to_paragraph($placeholder, $paragraph_number, $content, $mode = 'before', $post_id = null)
	{
		// Check if this is a genuine new insertion (not already in content AND not already marked as inserted)
		$already_in_content = \strpos($content, "ezoic-pub-ad-placeholder-{$placeholder->position_id}") !== false;
		$was_previously_inserted = Ezoic_AdTester::is_placement_inserted($placeholder->position_id);

		// Get markup for the placeholder
		$placeholder_markup		= $placeholder->embed_code(2);
		$placeholder_markup_len	= ez_strlen($placeholder_markup);
		$placement_paragraph		= -1;

		// Attempt to parse the placement display option
		if (ez_strlen($paragraph_number) > 0 && is_numeric($paragraph_number)) {
			$placement_paragraph = (int) $paragraph_number;
		} else {
			Ezoic_Integration_Logger::console_debug(
				"Insertion failed: Invalid paragraph '{$paragraph_number}' (must be numeric)",
				'Content Ads',
				'warn',
				$placeholder->position_id
			);
			return $content;
		}

		// If the placement display option is out of bounds, return the content
		if ($placement_paragraph < 1 || $placement_paragraph > \count($this->paragraphs)) {
			// Only show paragraph limit warning once per content processing
			if (!$this->paragraph_limit_warning_shown) {
				Ezoic_Integration_Logger::console_debug(
					"Paragraph {$placement_paragraph} not found (only " . \count($this->paragraphs) . " available). Additional paragraph insertions may also fail.",
					'Content Ads',
					'warn'
				);
				$this->paragraph_limit_warning_shown = true;
			}
			return $content;
		}

		// Select paragraph (convert to 0-based index)
		$paragraph_index = $placement_paragraph - 1;
		$target_paragraph = $this->paragraphs[$paragraph_index];

		// Determine insertion location
		$position = -1;
		if ($mode === 'before') {
			$position = $target_paragraph->open;
		} else {
			$position = $target_paragraph->close;
		}

		// Insert placeholder
		$original_content_length = strlen($content);
		$insertion_position = $position + $this->position_offset;

		// Use substr_replace instead of ez_substr_replace because tag parser returns BYTE positions but ez_substr_replace uses mb_substr which expects CHARACTER positions
		$content = \substr_replace($content, $placeholder_markup, $insertion_position, 0);
		$this->position_offset += $placeholder_markup_len;

		// Check if insertion actually happened
		$new_content_length = strlen($content);
		if ($new_content_length > $original_content_length) {
			Ezoic_Integration_Logger::track_insertion($placeholder->position_id);
			Ezoic_AdTester::mark_placement_inserted($placeholder->position_id, $post_id);
			// Only log if this is a truly new insertion (not already in content AND not previously marked as inserted)
			if (!$already_in_content && !$was_previously_inserted) {
				Ezoic_Integration_Logger::console_debug(
					"Inserted {$mode} paragraph {$placement_paragraph}",
					'Content Ads',
					'info',
					$placeholder->position_id
				);
			}
		} else {
			Ezoic_Integration_Logger::console_debug(
				"Failed insertion: Content unchanged (insertion error)",
				'Content Ads',
				'warn',
				$placeholder->position_id
			);
		}

		if (defined('EZOIC_DEBUG') && EZOIC_DEBUG) {
			$debugInfo = "";
			$debugInfo .= 'Placeholder ID: ' . $placeholder->position_id . PHP_EOL;
			$debugInfo .= 'Paragraph Insertion Number: ' . $paragraph_number . PHP_EOL;
			$debugInfo .= 'Mode: ' . $mode . PHP_EOL;
			$commented_content = "<!--[if IE 3 ]>Debugging Placeholder Insert: \n" . print_r($debugInfo, true) . "\n<![endif]-->";
			$content = $content . $commented_content;
		}

		return $content;
	}

	/**
	 * Extracts paragraphs and filters them based on filter rules
	 */
	private function get_paragraphs($content)
	{
		$filters = array();

		// Extract filter rules
		if (!empty($this->config->parent_filters)) {
			foreach ($this->config->parent_filters as $filter) {
				$parsed_filter = Ezoic_AdTester_Parent_Filter::parse_filter($filter);
				if ($parsed_filter !== null) {
					$filters[] = $parsed_filter;
				}
			}
		}

		// Extract paragraphs
		$paragraphs = Ezoic_AdTester_Tag_Parser::parse($content, $this->config->paragraph_tags);

		// Final list of counted paragraphs
		$filtered_paragraphs = array();

		// Evaluate parent filters
		if (count($filters) > 0) {
			// Evaluate every paragraph
			foreach ($paragraphs as $paragraph) {
				$paragraph_valid = true;

				// Apply parent filters
				for ($idx = 0; $paragraph_valid && $idx < count($filters); $idx++) {
					$filter = $filters[$idx];
					$paragraph_valid = !$filter->is_valid($paragraph);
				}

				// Apply word count filter (skip for img tags since they don't have meaningful word counts)
				if ($paragraph_valid && isset($this->config->skip_word_count) && $this->config->skip_word_count > 0 && $paragraph->tag !== 'img') {
					$sub_content = \ez_substr($content, $paragraph->open, $paragraph->close - $paragraph->open);
					$word_count = \ez_word_count($sub_content);

					if ($word_count < $this->config->skip_word_count) {
						$paragraph_valid = false;
					}
				}

				// Record paragraph if valid
				if ($paragraph_valid) {
					$filtered_paragraphs[] = $paragraph;
				}
			}
		} else {
			// No filters, apply only word count filter if configured
			if (isset($this->config->skip_word_count) && $this->config->skip_word_count > 0) {
				foreach ($paragraphs as $paragraph) {
					$paragraph_valid = true;

					// Apply word count filter (skip for img tags)
					if ($paragraph->tag !== 'img') {
						$sub_content = \ez_substr($content, $paragraph->open, $paragraph->close - $paragraph->open);
						$word_count = \ez_word_count($sub_content);

						if ($word_count < $this->config->skip_word_count) {
							$paragraph_valid = false;
						}
					}

					if ($paragraph_valid) {
						$filtered_paragraphs[] = $paragraph;
					}
				}

				return $filtered_paragraphs;
			}

			return $paragraphs;
		}

		return $filtered_paragraphs;
	}
}
