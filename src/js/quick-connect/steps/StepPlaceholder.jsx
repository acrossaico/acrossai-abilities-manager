/**
 * Feature 099 — placeholder for a screen not yet implemented.
 *
 * The wizard shell, router, guard context, and REST layer land in Phase 2; the
 * individual screens land in Phases 3-7. Rather than leaving `App.jsx` with
 * dangling imports (which would break the build at every phase boundary), any
 * step without a real component yet renders this.
 *
 * Each phase replaces one entry in `App.jsx`'s `stepRegistry`. When the registry
 * has no placeholders left, delete this file.
 *
 * @package
 */

import { __, sprintf } from '@wordpress/i18n';
import Notice from '../components/Notice.jsx';

/**
 * Placeholder screen.
 *
 * @param {Object} props       Component props.
 * @param {string} props.step  Step id being stood in for.
 * @param {string} props.title Human-readable screen name.
 * @return {Element} Placeholder content.
 */
const StepPlaceholder = ({ step, title }) => (
	<div>
		<h2 className="qs__step-title">{title}</h2>
		<Notice status="info">
			{sprintf(
				/* translators: %s: step identifier. */
				__(
					'This screen (step %s) is not built yet. The wizard shell, navigation, and data layer are in place.',
					'acrossai-abilities-manager'
				),
				step
			)}
		</Notice>
	</div>
);

export default StepPlaceholder;
