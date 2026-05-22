<?php
/**
 * Review Carousel module partial
 *
 * Caller sets $module before including:
 * $module = [
 *   'id'       => 'reviews',                // unique section id (required)
 *   'modifier' => 'review-carousel--light', // BEM modifier class (optional)
 *   'heading'  => 'What our customers say', // section heading (required)
 *   'reviews'  => [
 *     ['name' => 'Jane D.', 'text' => '"Great product."'],
 *   ],
 * ];
 *
 * The track divs are populated by initReviewCarousel() in site.js.
 * Base CSS lives in global.css under the MODULE STYLES section.
 * JS initialiser lives in site.js under MODULE INITIALISERS.
 */
$sectionId = htmlspecialchars($module['id'] ?? 'reviews', ENT_QUOTES, 'UTF-8');
$mod       = $module['modifier'] ?? '';
$modClass  = $mod ? ' ' . htmlspecialchars($mod, ENT_QUOTES, 'UTF-8') : '';
$headId    = $sectionId . '-heading';
$reviews   = $module['reviews'] ?? [];
// Encode reviews for JS initialiser via data attribute — avoids inline <script>
$reviewsJson = htmlspecialchars(json_encode($reviews, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
?>
<section id="<?= $sectionId ?>" class="review-carousel<?= $modClass ?>" aria-labelledby="<?= htmlspecialchars($headId, ENT_QUOTES, 'UTF-8') ?>">
  <div class="review-carousel__inner">
    <h2 id="<?= htmlspecialchars($headId, ENT_QUOTES, 'UTF-8') ?>" class="review-carousel__heading"><?= htmlspecialchars($module['heading'], ENT_QUOTES, 'UTF-8') ?></h2>
    <div class="review-carousel__track-wrapper" aria-live="polite" aria-atomic="false"
         data-module="review-carousel" data-reviews="<?= $reviewsJson ?>">
      <div class="review-carousel__track-desktop"></div>
      <div class="review-carousel__track-mobile"></div>
    </div>
    <div class="review-carousel__nav" role="group" aria-label="Carousel navigation">
      <button class="review-carousel__arrow review-carousel__arrow--prev" aria-label="Previous reviews">
        <svg aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
      </button>
      <div class="review-carousel__dots" role="tablist" aria-label="Review pages"></div>
      <button class="review-carousel__arrow review-carousel__arrow--next" aria-label="Next reviews">
        <svg aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
      </button>
    </div>
  </div>
</section>
