<?php
/**
 * Yoast SEO: site report, live page analysis and focus keyphrase.
 *
 * Yoast's own abilities (read and update SEO data, scores) are enabled by guides/yoast-seo.md;
 * these ones fill the gaps.
 *
 * @package EoBots
 */

namespace EoBots\Includes\Integrations;

use EoBots\Includes\Abilities\Content;
use EoBots\Includes\Abilities\Registry;
use EoBots\Includes\Journal;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registered only when Yoast SEO is active.
 */
class Yoast {

	const KEYPHRASE_META = '_yoast_wpseo_focuskw';

	/**
	 * Registers the abilities.
	 */
	public static function register() {
		$id_schema = array(
			'type'    => 'integer',
			'minimum' => 1,
		);

		Registry::add(
			'seo-report',
			array(
				'label'               => __( 'SEO report of the site', 'eo-bots' ),
				'description'         => 'Site-wide SEO health from Yoast SEO: score distribution, pages without focus keyphrase or meta description, duplicate keyphrases, pages without internal links pointing to them, noindex pages, and blocking site settings. Each list gives the page IDs to work on.',
				'input_schema'        => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'default'              => array(),
				),
				'execute_callback'    => array( __CLASS__, 'report' ),
				'permission_callback' => array( Registry::class, 'can_edit' ),
				'meta'                => array(
					'annotations' => Registry::annotations( true ),
					'eobots'      => array(
						'confirm'  => 'never',
						'progress' => __( 'Analyzing the SEO of your site…', 'eo-bots' ),
					),
				),
			)
		);

		Registry::add(
			'seo-review',
			array(
				'label'               => __( 'Analyze the SEO of a page', 'eo-bots' ),
				'description'         => 'Analyzes the current content of a page for its focus keyphrase, right now (Yoast scores only refresh when someone opens the page in the editor): SEO title and meta description, slug, introduction, subheadings, length, keyphrase density, images, links. Returns what passes and what to fix.',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'        => $id_schema,
						'keyphrase' => array(
							'type'        => 'string',
							'description' => 'Keyphrase to test; defaults to the page focus keyphrase.',
						),
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => array( __CLASS__, 'review' ),
				'permission_callback' => array( Content::class, 'can_edit_post' ),
				'meta'                => array(
					'annotations' => Registry::annotations( true ),
					'eobots'      => array(
						'confirm'  => 'never',
						'progress' => __( 'Analyzing the SEO of the page…', 'eo-bots' ),
					),
				),
			)
		);

		Registry::add(
			'set-focus-keyphrase',
			array(
				'label'               => __( 'Set the focus keyphrase', 'eo-bots' ),
				'description'         => 'Sets the Yoast SEO focus keyphrase of a page: the search query the page should rank for. One unique keyphrase per page.',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'        => $id_schema,
						'keyphrase' => array(
							'type'        => 'string',
							'description' => 'Two to four words people type in Google; empty to remove.',
						),
					),
					'required'             => array( 'id', 'keyphrase' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => array( __CLASS__, 'set_keyphrase' ),
				'permission_callback' => array( Content::class, 'can_edit_post' ),
				'meta'                => array(
					'annotations' => Registry::annotations( false ),
					'eobots'      => array(
						'confirm'  => 'never',
						'progress' => __( 'Setting the focus keyphrase…', 'eo-bots' ),
					),
				),
			)
		);
	}

	/* ---------- Site report ---------- */

	/**
	 * Site-wide SEO report from the Yoast indexables.
	 *
	 * @return array<string, mixed>
	 */
	public static function report() {
		global $wpdb;

		$table = $wpdb->prefix . 'yoast_indexable';
		$types = Content::post_types();
		$in    = implode( ',', array_fill( 0, count( $types ), '%s' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT object_id, object_sub_type, title, description, primary_focus_keyword, primary_focus_keyword_score, readability_score, is_cornerstone, is_robots_noindex, incoming_link_count, estimated_reading_time_minutes FROM {$table} WHERE object_type = 'post' AND post_status = 'publish' AND object_sub_type IN ($in)", $types ), ARRAY_A );

		$front         = (int) get_option( 'page_on_front' );
		$seo           = array_fill_keys( array( 'good', 'ok', 'bad', 'not_analyzed' ), 0 );
		$read          = $seo;
		$lists         = array(
			'bad_seo'             => array(),
			'no_focus_keyphrase'  => array(),
			'no_meta_description' => array(),
			'no_incoming_links'   => array(),
			'noindex'             => array(),
		);
		$keyphrases    = array();
		$cornerstone   = 0;
		$unknown_links = 0;

		foreach ( $rows as $row ) {
			$id    = (int) $row['object_id'];
			$item  = array(
				'id'    => $id,
				'title' => get_the_title( $id ),
				'type'  => $row['object_sub_type'],
			);
			$score = self::bucket( $row['primary_focus_keyword'] ? $row['primary_focus_keyword_score'] : null );
			++$seo[ $score ];
			++$read[ self::bucket( $row['readability_score'] ) ];

			if ( 'bad' === $score ) {
				$lists['bad_seo'][] = $item;
			}
			if ( ! $row['primary_focus_keyword'] ) {
				$lists['no_focus_keyphrase'][] = $item;
			} else {
				$keyphrases[ strtolower( $row['primary_focus_keyword'] ) ][] = $id;
			}
			if ( '' === trim( (string) $row['description'] ) ) {
				$lists['no_meta_description'][] = $item;
			}
			// NULL means Yoast has not counted the links yet, not zero links.
			if ( null === $row['incoming_link_count'] ) {
				++$unknown_links;
			} elseif ( ! (int) $row['incoming_link_count'] && $id !== $front ) {
				$lists['no_incoming_links'][] = $item;
			}
			if ( (int) $row['is_robots_noindex'] ) {
				$lists['noindex'][] = $item;
			}
			$cornerstone += (int) $row['is_cornerstone'];
		}

		$duplicates = array_filter( $keyphrases, static fn( $ids ) => count( $ids ) > 1 );

		$counts = array();
		foreach ( $lists as $key => $items ) {
			$counts[ $key ] = count( $items );
			$lists[ $key ]  = array_slice( $items, 0, 8 );
		}

		$notes = array( 'Yoast computes scores when a page is saved in the editor: "not_analyzed" pages were never optimized or never opened since Yoast was installed. Use seo-review for a live analysis.' );
		if ( $unknown_links > count( $rows ) / 2 ) {
			$notes[] = "Internal links are not counted yet for $unknown_links pages, so no_incoming_links is incomplete. Running Yoast's \"SEO data optimization\" (Yoast SEO › Tools) completes it.";
		}

		return array(
			'published_pages'      => count( $rows ),
			'seo_scores'           => $seo,
			'readability_scores'   => $read,
			'counts'               => $counts,
			'cornerstone_pages'    => $cornerstone,
			'duplicate_keyphrases' => $duplicates,
			'examples'             => $lists,
			'site_issues'          => self::site_issues(),
			'notes'                => $notes,
		);
	}

	/**
	 * Yoast score bucket.
	 *
	 * @param mixed $score Score 0-100 or null.
	 * @return string
	 */
	private static function bucket( $score ) {
		if ( null === $score || '' === $score || ! (int) $score ) {
			return 'not_analyzed';
		}
		$score = (int) $score;
		return $score > 70 ? 'good' : ( $score > 40 ? 'ok' : 'bad' );
	}

	/**
	 * Settings that block or hurt SEO for the whole site.
	 *
	 * @return string[]
	 */
	private static function site_issues() {
		$issues = array();
		if ( ! get_option( 'blog_public' ) ) {
			$issues[] = 'CRITICAL: Settings › Reading asks search engines not to index the site. Nothing will appear in Google until it is unchecked (only do it when the site is ready).';
		}
		if ( '' === (string) get_option( 'permalink_structure' ) ) {
			$issues[] = 'Plain permalinks (?page_id=12): switch to "Post name" in Settings › Permalinks for readable URLs.';
		}
		if ( class_exists( '\WPSEO_Options' ) && ! \WPSEO_Options::get( 'enable_xml_sitemap' ) ) {
			$issues[] = 'The Yoast XML sitemap is off: search engines discover pages more slowly.';
		}
		$tagline = get_bloginfo( 'description' );
		if ( '' === $tagline || in_array( $tagline, array( 'Just another WordPress site', 'Un site utilisant WordPress' ), true ) ) {
			$issues[] = 'The site tagline is empty or the WordPress default: it often appears in titles and search results.';
		}
		return $issues;
	}

	/* ---------- Page analysis ---------- */

	/**
	 * Live SEO analysis of one page.
	 *
	 * @param array<string, mixed> $input Page ID and optional keyphrase.
	 * @return array<string, mixed>
	 */
	public static function review( $input ) {
		$post      = get_post( (int) $input['id'] );
		$keyphrase = trim( (string) ( $input['keyphrase'] ?? get_post_meta( $post->ID, self::KEYPHRASE_META, true ) ) );
		$meta      = function_exists( 'YoastSEO' ) ? YoastSEO()->meta->for_post( $post->ID ) : null;
		$title     = $meta ? (string) $meta->title : get_the_title( $post );
		$desc      = $meta ? (string) $meta->description : '';
		$html      = apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$text      = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $html ) ) );
		$words     = $text ? count( preg_split( '/\s+/u', $text ) ) : 0;

		$pass = array();
		$fix  = array();
		$add  = static function ( $ok, $good, $bad ) use ( &$pass, &$fix ) {
			if ( $ok ) {
				$pass[] = $good;
			} else {
				$fix[] = $bad;
			}
		};

		$add( $words >= 300, "Length: $words words.", "Only $words words: aim for 300 or more of useful text." );
		$add( mb_strlen( $title ) >= 30 && mb_strlen( $title ) <= 60, 'SEO title length is good (' . mb_strlen( $title ) . ' characters).', 'SEO title is ' . mb_strlen( $title ) . ' characters ("' . $title . '"): aim for 30 to 60.' );
		$add( '' !== $desc, 'Meta description is set.', 'No meta description: write one (120 to 156 characters, with the keyphrase and a reason to click).' );
		if ( '' !== $desc ) {
			$len = mb_strlen( $desc );
			$add( $len >= 120 && $len <= 156, "Meta description length is good ($len characters).", "Meta description is $len characters: aim for 120 to 156." );
		}

		$images = preg_match_all( '/<img\b[^>]*>/i', $html, $img_tags );
		$add( $images > 0, "$images image(s).", 'No image: add at least one relevant image.' );
		$links    = preg_match_all( '/<a\b[^>]*href="([^"]+)"/i', $html, $hrefs );
		$home     = wp_parse_url( home_url(), PHP_URL_HOST );
		$internal = 0;
		$outbound = 0;
		foreach ( $hrefs[1] as $href ) {
			$host = wp_parse_url( $href, PHP_URL_HOST );
			if ( ! $host || $host === $home ) {
				++$internal;
			} else {
				++$outbound;
			}
		}
		$add( $internal > 0, "$internal internal link(s).", 'No internal link: link to 2 or 3 related pages of the site.' );
		$add( $outbound > 0, "$outbound outbound link(s).", 'No outbound link: one link to a trusted external source can help (optional).' );

		if ( '' === $keyphrase ) {
			$fix[] = 'No focus keyphrase: choose the 2 to 4 words people would search to find this page, then set it with set-focus-keyphrase.';
		} else {
			// Whole words only: "cours" must not match "parcours".
			$k  = ' ' . self::normalize( $keyphrase ) . ' ';
			$in = static fn( $haystack ) => false !== strpos( ' ' . self::normalize( $haystack ) . ' ', $k );

			$add( $in( $title ), 'Keyphrase in the SEO title.', 'Keyphrase missing from the SEO title: put it near the start.' );
			if ( '' !== $desc ) {
				$add( $in( $desc ), 'Keyphrase in the meta description.', 'Keyphrase missing from the meta description.' );
			}
			$add( false !== strpos( $post->post_name, sanitize_title( $keyphrase ) ) || $in( str_replace( '-', ' ', $post->post_name ) ), 'Keyphrase in the slug.', 'Keyphrase missing from the slug (' . $post->post_name . ').' );

			preg_match( '/<p\b[^>]*>(.*?)<\/p>/is', $html, $first );
			$add( $in( wp_strip_all_tags( $first[1] ?? '' ) ), 'Keyphrase in the introduction.', 'Keyphrase missing from the first paragraph.' );

			preg_match_all( '/<h[2-3]\b[^>]*>(.*?)<\/h[2-3]>/is', $html, $subs );
			$with = count( array_filter( $subs[1], static fn( $h ) => $in( wp_strip_all_tags( $h ) ) ) );
			$add( $with > 0, "Keyphrase in $with subheading(s).", 'Keyphrase (or a close variant) missing from the H2/H3 subheadings.' );

			$count   = substr_count( ' ' . self::normalize( $text ) . ' ', $k );
			$density = $words ? round( 100 * $count * count( explode( ' ', trim( $k ) ) ) / $words, 1 ) : 0;
			$add( $density >= 0.5 && $density <= 3, "Keyphrase density $density% ($count times).", "Keyphrase density is $density% ($count times): aim for 0.5 to 3%, written naturally." );

			$alt = count( array_filter( $img_tags[0] ?? array(), static fn( $tag ) => preg_match( '/alt="([^"]*)"/i', $tag, $a ) && $in( $a[1] ) ) );
			$add( ! $images || $alt > 0, 'Keyphrase in an image alt text.', 'No image alt text contains the keyphrase.' );

			$others = get_posts(
				array(
					'post_type'   => Content::post_types(),
					'post_status' => array( 'publish', 'draft', 'private', 'future' ),
					'meta_key'    => self::KEYPHRASE_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'  => $keyphrase, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					'exclude'     => array( $post->ID ),
					'fields'      => 'ids',
					'numberposts' => 3,
				)
			);
			$add( ! $others, 'Keyphrase not used by another page.', 'Keyphrase already targeted by page(s) #' . implode( ', #', $others ) . ': give each page its own keyphrase.' );
		}

		if ( get_post_meta( $post->ID, '_yoast_wpseo_meta-robots-noindex', true ) === '1' ) {
			$fix[] = 'This page is set to noindex: it will not appear in search results.';
		}

		return array(
			'keyphrase'        => $keyphrase ? $keyphrase : null,
			'seo_title'        => $title,
			'meta_description' => $desc ? $desc : null,
			'words'            => $words,
			'to_fix'           => $fix,
			'passing'          => $pass,
		);
	}

	/**
	 * Lowercase text without accents or punctuation, for matching.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function normalize( $text ) {
		$text = remove_accents( html_entity_decode( (string) $text, ENT_QUOTES, 'UTF-8' ) );
		$text = strtolower( preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $text ) );
		return trim( preg_replace( '/\s+/', ' ', $text ) );
	}

	/* ---------- Focus keyphrase ---------- */

	/**
	 * Sets the focus keyphrase (Yoast refreshes its index when the meta changes).
	 *
	 * @param array<string, mixed> $input Page ID and keyphrase.
	 * @return array<string, mixed>
	 */
	public static function set_keyphrase( $input ) {
		$id        = (int) $input['id'];
		$keyphrase = sanitize_text_field( (string) $input['keyphrase'] );
		$previous  = (string) get_post_meta( $id, self::KEYPHRASE_META, true );

		if ( '' === $keyphrase ) {
			delete_post_meta( $id, self::KEYPHRASE_META );
		} else {
			update_post_meta( $id, self::KEYPHRASE_META, $keyphrase );
		}

		$journal_id = Journal::record(
			array(
				'ability'     => 'eobots/set-focus-keyphrase',
				'object_type' => 'post',
				'object_id'   => $id,
				/* translators: 1: keyphrase, 2: content title. */
				'summary'     => sprintf( __( 'Focus keyphrase “%1$s” set on “%2$s”', 'eo-bots' ), $keyphrase, get_the_title( $id ) ),
				'undo'        => array(
					'action' => 'meta',
					'key'    => self::KEYPHRASE_META,
					'value'  => $previous,
				),
			)
		);

		return array(
			'id'         => $id,
			'keyphrase'  => $keyphrase,
			'previous'   => $previous ? $previous : null,
			'journal_id' => $journal_id,
		);
	}
}
