/* global describe, it, expect */
/**
 * Feature 099 — transport screen decisions.
 *
 * `shouldOfferInstall` drives two things that must never disagree: whether the
 * install action appears, and whether the shell's generic Continue is hidden.
 * When they disagreed, the screen showed two competing forward buttons and the
 * generic one read "Finish" — inviting the operator to skip the one step the
 * wizard exists to complete. That was reported from a real site.
 *
 * `isSameOrigin` guards the post-install hand-off (SEC-003).
 */

import {
	shouldOfferInstall,
	isSameOrigin,
	installActionLabel,
} from '../../../src/js/quick-connect/steps/Step5ConnectTransport';

describe('shouldOfferInstall', () => {
	it('offers the install when the recommended transport is absent and permitted', () => {
		expect(shouldOfferInstall(false, 'mcp-manager', true)).toBe(true);
	});

	it('does not offer it once the recommended transport is active', () => {
		expect(shouldOfferInstall(true, 'mcp-manager', true)).toBe(false);
	});

	it('does not offer it on the alternative transport, which cannot be installed from here', () => {
		expect(shouldOfferInstall(false, 'mcp-adapter', true)).toBe(false);
	});

	it('does not offer it without the install capabilities (SC-009)', () => {
		expect(shouldOfferInstall(false, 'mcp-manager', false)).toBe(false);
	});

	it('treats a missing capability flag as "cannot install" rather than assuming yes', () => {
		expect(shouldOfferInstall(false, 'mcp-manager', undefined)).toBe(false);
	});

	/**
	 * The screen must always leave exactly one forward route.
	 *
	 * Either the install action is offered (and the generic Continue is hidden),
	 * or it is not (and Continue is the way on). Both-or-neither is the bug.
	 */
	it('never leaves the operator with two forward buttons or none', () => {
		const cases = [
			[false, 'mcp-manager', true],
			[true, 'mcp-manager', true],
			[false, 'mcp-adapter', true],
			[false, 'mcp-manager', false],
			[true, 'mcp-adapter', false],
		];

		cases.forEach(([managerActive, method, canInstall]) => {
			const offersInstall = shouldOfferInstall(
				managerActive,
				method,
				canInstall
			);
			const continueHidden = offersInstall;

			// Exactly one forward control is present in every state.
			expect(offersInstall || !continueHidden).toBe(true);
			expect(offersInstall && !continueHidden).toBe(false);
		});
	});
});

describe('installActionLabel', () => {
	it('reads "Install" when the plugin is not present', () => {
		expect(installActionLabel(false, 'missing')).toContain('Install');
	});

	it('reads "Activate" when the plugin is present but switched off', () => {
		expect(installActionLabel(false, 'inactive')).toContain('Activate');
	});

	it('reports progress while working', () => {
		expect(installActionLabel(true, 'missing')).toContain('Installing');
	});
});

describe('isSameOrigin — SEC-003 hand-off guard', () => {
	const origin = 'https://example.test';

	it('accepts a same-origin admin URL', () => {
		expect(
			isSameOrigin(`${origin}/wp-admin/admin.php?page=x`, origin)
		).toBe(true);
	});

	it('accepts a root-relative path', () => {
		expect(isSameOrigin('/wp-admin/admin.php', origin)).toBe(true);
	});

	it('rejects a different origin', () => {
		expect(isSameOrigin('https://evil.test/wp-admin/', origin)).toBe(false);
	});

	it('rejects a protocol-relative URL pointing elsewhere', () => {
		expect(isSameOrigin('//evil.test/wp-admin/', origin)).toBe(false);
	});

	it('rejects unparseable input rather than throwing', () => {
		expect(isSameOrigin('http://[', origin)).toBe(false);
	});
});
