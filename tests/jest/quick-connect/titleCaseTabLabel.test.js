/**
 * Feature 099 — JS half of the paired tab-group label contract.
 *
 * SHARED_FIXTURE below is duplicated verbatim from
 * tests/phpunit/Utilities/Test_Tab_Group_Label.php::shared_fixture().
 * Both sides must produce identical output for every entry, or the wizard's
 * integrations screen disagrees with the Integrations admin page (spec SC-004).
 *
 * If you change one rule, this pair fails. That is the point — do not "fix" it
 * by editing only one side.
 */

import { titleCaseTabLabel } from '../../../src/js/shared/titleCaseTabLabel';

// Keep in sync with Test_Tab_Group_Label::shared_fixture().
const SHARED_FIXTURE = {
	core: 'Core',
	blocks: 'Blocks',
	'content-search': 'Content Search',
	'file-manager': 'File Manager',
	'site-health': 'Site Health',
	database: 'Database',
	'mailerpress-pro': 'Mailerpress Pro',
};

describe('titleCaseTabLabel', () => {
	it.each(Object.entries(SHARED_FIXTURE))(
		'formats %s to match the PHP rule',
		(key, expected) => {
			expect(titleCaseTabLabel(key)).toBe(expected);
		}
	);

	it('returns an empty string for an empty key', () => {
		expect(titleCaseTabLabel('')).toBe('');
	});

	it('returns an empty string for undefined rather than throwing', () => {
		expect(titleCaseTabLabel(undefined)).toBe('');
	});

	it('is idempotent for single words', () => {
		const once = titleCaseTabLabel('core');
		expect(titleCaseTabLabel(once)).toBe(once);
	});

	it('capitalises every hyphen-separated word, not just the first', () => {
		expect(titleCaseTabLabel('a-b-c')).toBe('A B C');
	});
});
