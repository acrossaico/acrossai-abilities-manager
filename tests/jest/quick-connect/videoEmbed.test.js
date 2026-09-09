/* global describe, it, expect */
/**
 * Feature 099 — walkthrough embed hardening.
 *
 * DEC-ADMIN-THIRD-PARTY-EMBED (Active) makes four things mandatory for any
 * third-party embed on an admin screen. Three of them are structural and are
 * asserted here; the fourth (the click-to-load facade) is enforced by the
 * component only rendering an iframe after a click.
 *
 * These assertions exist because a well-meaning "simplification" back to
 * youtube.com would silently reintroduce security finding SEC-001 — an
 * unconsented third-party request from an authenticated admin session that
 * leaks the site's admin URL via Referer.
 */

import {
	buildEmbedUrl,
	buildWatchUrl,
} from '../../../src/js/quick-connect/components/VideoEmbed';

const VIDEO_ID = '6nDuURDNmLc';
const PLAYLIST = 'PLL-i34ne1J0c';

describe('buildEmbedUrl — DEC-ADMIN-THIRD-PARTY-EMBED', () => {
	it('uses the privacy-preserving host, never youtube.com', () => {
		const url = buildEmbedUrl(VIDEO_ID);

		expect(url.startsWith('https://www.youtube-nocookie.com/embed/')).toBe(
			true
		);
		expect(url).not.toContain('//www.youtube.com/');
	});

	it('includes the video id', () => {
		expect(buildEmbedUrl(VIDEO_ID)).toContain(VIDEO_ID);
	});

	it('carries the playlist when one is given', () => {
		expect(buildEmbedUrl(VIDEO_ID, PLAYLIST)).toContain(
			`list=${PLAYLIST}`
		);
	});

	it('omits the playlist parameter entirely when none is given', () => {
		expect(buildEmbedUrl(VIDEO_ID)).not.toContain('list=');
	});

	it('suppresses related-video suggestions', () => {
		expect(buildEmbedUrl(VIDEO_ID)).toContain('rel=0');
	});

	it('mutes gesture-initiated playback so browsers do not block it', () => {
		const url = buildEmbedUrl(VIDEO_ID);

		expect(url).toContain('autoplay=1');
		expect(url).toContain('mute=1');
	});

	it('percent-encodes an id containing URL-significant characters', () => {
		expect(buildEmbedUrl('a/b?c')).not.toContain('a/b?c');
	});
});

describe('buildWatchUrl — FR-019a fallback', () => {
	it('points at the public watch page so a blocked embed still has a route', () => {
		const url = buildWatchUrl(VIDEO_ID);

		expect(url.startsWith('https://www.youtube.com/watch')).toBe(true);
		expect(url).toContain(`v=${VIDEO_ID}`);
	});

	it('preserves the playlist context', () => {
		expect(buildWatchUrl(VIDEO_ID, PLAYLIST)).toContain(`list=${PLAYLIST}`);
	});
});
