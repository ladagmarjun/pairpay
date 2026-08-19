import type { SVGAttributes } from 'react';

/**
 * The peso sign, drawn as a monogram: the stem and bowl of a `P` with the two
 * bars of `₱` running through it. One shape, no strokes, so it survives being
 * dropped to 16px in a favicon and inherits `fill-current` wherever it sits.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
            <path
                fillRule="evenodd"
                clipRule="evenodd"
                d="M11 2H22A7.5 7.5 0 0 1 22 17H16V28A2 2 0 0 1 14 30H11A2 2 0 0 1 9 28V4A2 2 0 0 1 11 2ZM16 6.5H22A3 3 0 0 1 22 12.5H16Z"
            />
            <rect x="3" y="6.4" width="13" height="2.4" rx="1.2" />
            <rect x="3" y="10.2" width="13" height="2.4" rx="1.2" />
        </svg>
    );
}
