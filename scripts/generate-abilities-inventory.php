<?php
/**
 * Regenerate docs/abilities-inventory.md from the ability source files.
 *
 * The inventory is a snapshot of every ability the plugin registers. It was
 * hand-maintained until Feature 101 and had drifted badly — it claimed 389
 * abilities across 24 namespaces when the source had ~452 across 26, and
 * omitted the entire Debugging folder. Nothing regenerated or verified it.
 *
 * Run from the plugin root:
 *
 *     php scripts/generate-abilities-inventory.php
 *
 * Values are read from the source rather than from a booted WordPress, so
 * this needs no database and no plugin activation. Abilities that inherit
 * `category` / `tab_group` from a base class via `self::CONST` are resolved
 * against that base class.
 *
 * @package AcrossAI_Abilities_Manager
 */

declare( strict_types=1 );

$root       = dirname( __DIR__ );
$source_dir = $root . '/includes/Abilities';
$out_file   = $root . '/docs/abilities-inventory.md';

/**
 * Pull the first single-quoted value for a given array key.
 *
 * @param string $key Array key, e.g. 'tab_group'.
 * @param string $src File contents.
 * @return string Value, or '' when absent.
 */
function acrossai_first_value( string $key, string $src ): string {
	$pattern = "/'" . preg_quote( $key, '/' ) . "'\s*=>\s*'([^']+)'/";
	return preg_match( $pattern, $src, $m ) ? $m[1] : '';
}

/**
 * Pull a class constant's literal value.
 *
 * @param string $const Constant name.
 * @param string $src   File contents.
 * @return string Value, or '' when absent.
 */
function acrossai_const_value( string $const, string $src ): string {
	$pattern = '/const\s+' . preg_quote( $const, '/' ) . "\s*=\s*'([^']+)'/";
	return preg_match( $pattern, $src, $m ) ? $m[1] : '';
}

// Base-class constants, resolved once so `self::CATEGORY` subclasses inherit.
$base_defaults = array();
foreach ( glob( $source_dir . '/*/Base_*.php' ) as $base_file ) {
	$folder = basename( dirname( $base_file ) );
	$src    = (string) file_get_contents( $base_file );

	$category  = acrossai_const_value( 'CATEGORY', $src ) ?: acrossai_first_value( 'category', $src );
	$tab_group = acrossai_const_value( 'TAB_GROUP', $src ) ?: acrossai_first_value( 'tab_group', $src );

	// Some families compose the slug in the base: 'rank-math/' . $this->slug().
	$slug_prefix = '';
	if ( preg_match( "/'([a-z0-9-]+\/)'\s*\.\s*\\\$this->slug\(\)/", $src, $m ) ) {
		$slug_prefix = $m[1];
	}

	if ( '' !== $category || '' !== $tab_group || '' !== $slug_prefix ) {
		$base_defaults[ $folder ] = array(
			'category'    => $category ?: ( $base_defaults[ $folder ]['category'] ?? '' ),
			'tab_group'   => $tab_group ?: ( $base_defaults[ $folder ]['tab_group'] ?? '' ),
			'slug_prefix' => $slug_prefix ?: ( $base_defaults[ $folder ]['slug_prefix'] ?? '' ),
		);
	}
}

$rows = array();
foreach ( glob( $source_dir . '/*', GLOB_ONLYDIR ) as $dir ) {
	$folder = basename( $dir );
	if ( in_array( $folder, array( 'Utilities', 'Rest', 'Integrations' ), true ) ) {
		continue;
	}

	foreach ( glob( $dir . '/*.php' ) as $file ) {
		$name = basename( $file, '.php' );
		if ( 'Category_Registrar' === $name || str_starts_with( $name, 'Base_' ) ) {
			continue;
		}

		$src  = (string) file_get_contents( $file );
		$slug = acrossai_first_value( 'name', $src );

		// Subclasses that only supply the suffix: slug() plus the base's prefix.
		$prefix = $base_defaults[ $folder ]['slug_prefix'] ?? '';
		if ( '' === $slug && '' !== $prefix
			&& preg_match( "/function slug\(\)\s*:\s*string\s*\{\s*return\s*'([^']+)'/", $src, $m )
		) {
			$slug = $prefix . $m[1];
		}

		// Subclasses that supply the WHOLE slug, namespace included. A suite spanning two slug
		// namespaces cannot use a single base prefix — the ACF suite is `custom-fields/*` for field
		// data and `blocks/*` for block operations — so its base concatenates nothing and each
		// ability returns its full name. Without this branch every such ability is silently skipped
		// and the inventory under-reports.
		if ( '' === $slug
			&& preg_match( "/function slug\(\)\s*:\s*string\s*\{\s*return\s*'([a-z0-9-]+\/[a-z0-9-]+)'/", $src, $m )
		) {
			$slug = $m[1];
		}

		if ( '' === $slug || ! str_contains( $slug, '/' ) ) {
			continue;
		}

		$label = '';
		if ( preg_match( "/'label'\s*=>\s*__\(\s*'([^']+)'/", $src, $m ) ) {
			$label = $m[1];
		} elseif ( preg_match( "/'label'\s*=>\s*'([^']+)'/", $src, $m ) ) {
			$label = $m[1];
		}

		$category  = acrossai_first_value( 'category', $src ) ?: ( $base_defaults[ $folder ]['category'] ?? '' );
		$tab_group = acrossai_first_value( 'tab_group', $src ) ?: ( $base_defaults[ $folder ]['tab_group'] ?? '' );

		$rows[] = array(
			'namespace' => explode( '/', $slug )[0] . '/',
			'slug'      => $slug,
			'tab_group' => $tab_group,
			'sub_group' => acrossai_first_value( 'sub_group', $src ),
			'label'     => $label,
			'category'  => $category,
			'folder'    => $folder,
		);
	}
}

usort(
	$rows,
	static fn( array $a, array $b ): int => array( $a['namespace'], $a['slug'] ) <=> array( $b['namespace'], $b['slug'] )
);

// Summaries.
$by_namespace = array();
$by_group    = array();
foreach ( $rows as $row ) {
	$ns = $row['namespace'];
	$by_namespace[ $ns ]['count']                    = ( $by_namespace[ $ns ]['count'] ?? 0 ) + 1;
	$by_namespace[ $ns ]['categories'][ $row['category'] ] = true;
	$by_group[ $row['tab_group'] ]                  = ( $by_group[ $row['tab_group'] ] ?? 0 ) + 1;
}
ksort( $by_namespace );
arsort( $by_group );

$total = count( $rows );
$date  = gmdate( 'Y-m-d' );

$out  = "# Ability inventory\n\n";
$out .= "**Generated file — do not edit by hand.** Regenerate with:\n\n";
$out .= "```sh\nphp scripts/generate-abilities-inventory.php\n```\n\n";
$out .= "Snapshot taken {$date}. **Total abilities:** {$total} across " . count( $by_namespace ) . " topic namespaces.\n\n";
$out .= "Conditional integrations (Elementor, Rank Math, ACF) are listed here whether or not their\n";
$out .= "host plugin is active on any given site — this is a source inventory, not a runtime one.\n\n";

$out .= "## Groups\n\n";
$out .= "Tabs on the Ability Integrations screen. `tab_group` is assigned per ability, so a category\n";
$out .= "may legitimately span two groups (Feature 101, `DEC-ABILITY-GROUP-TAXONOMY`).\n\n";
$out .= "| Group | Abilities |\n|---|---:|\n";
foreach ( $by_group as $group => $count ) {
	$label = ucwords( str_replace( '-', ' ', (string) $group ) );
	$out  .= "| `{$group}` — {$label} | {$count} |\n";
}

$out .= "\n## Namespaces\n\n| Namespace | Count | WP category slug |\n|---|---:|---|\n";
foreach ( $by_namespace as $ns => $data ) {
	$cats = implode( ', ', array_map( static fn( $c ) => "`{$c}`", array_keys( array_filter( $data['categories'] ) ) ) );
	$out .= "| `{$ns}` | {$data['count']} | {$cats} |\n";
}

$out .= "\n## Every ability\n\n| Namespace | Slug | Family | Sub-group | Label |\n|---|---|---|---|---|\n";
foreach ( $rows as $row ) {
	$out .= "| `{$row['namespace']}` | `{$row['slug']}` | {$row['tab_group']} | {$row['sub_group']} | {$row['label']} |\n";
}

file_put_contents( $out_file, $out );

echo "Wrote {$out_file}\n";
echo "{$total} abilities across " . count( $by_namespace ) . " namespaces, " . count( $by_group ) . " groups.\n";
