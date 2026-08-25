// Single JS mirror of the style.css :root palette values for script-side
// consumers (confetti/particle generators build their colour arrays before
// mount, so getComputedStyle is not reliably available). Values MUST stay
// byte-identical to style.css / fynlaDesignGuide.md — change them there
// first, then here.
export const TOKENS = {
  raspberry500: '#E83E6D',
  spring300: '#6EE7B7',
  spring500: '#20B486',
  violet400: '#A78BFA',
  savannah500: '#E6C9A8',
};
