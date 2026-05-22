<?php
/**
 * CTA Band module partial
 *
 * Caller sets $module before including:
 * $module = [
 *   'id'          => 'clarity',                   // unique section id (required)
 *   'modifier'    => 'cta-band--raspberry',        // BEM modifier class (optional)
 *   'heading'     => '15 minutes to clarity',      // section heading (required)
 *   'heading_tag' => 'h2',                          // h2 by default (optional)
 *   'subtext'     => 'Start with the demo...',     // supporting copy (optional)
 *   'actions'     => [                             // one or more buttons (required)
 *     ['text' => 'Start your free trial', 'href' => '/register', 'primary' => true],
 *     ['text' => 'View pricing',           'href' => '/pricing',  'primary' => false],
 *   ],
 * ];
 *
 * Base CSS lives in global.css under the MODULE STYLES section.
 */
$sectionId = htmlspecialchars($module['id'] ?? 'cta-band', ENT_QUOTES, 'UTF-8');
$mod       = $module['modifier'] ?? '';
$modClass  = $mod ? ' ' . htmlspecialchars($mod, ENT_QUOTES, 'UTF-8') : '';
$tag       = $module['heading_tag'] ?? 'h2';
$headId    = $sectionId . '-heading';
?>
<section id="<?= $sectionId ?>" class="cta-band<?= $modClass ?>" aria-labelledby="<?= htmlspecialchars($headId, ENT_QUOTES, 'UTF-8') ?>">
  <div class="cta-band__inner">
    <<?= $tag ?> id="<?= htmlspecialchars($headId, ENT_QUOTES, 'UTF-8') ?>" class="cta-band__heading"><?= htmlspecialchars($module['heading'], ENT_QUOTES, 'UTF-8') ?></<?= $tag ?>>
    <?php if (!empty($module['subtext'])): ?>
    <p class="cta-band__subtext"><?= htmlspecialchars($module['subtext'], ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <?php if (!empty($module['actions'])): ?>
    <div class="cta-band__actions">
      <?php foreach ($module['actions'] as $action): ?>
        <?php
        $cls = !empty($action['primary']) ? 'cta-band__btn cta-band__btn--primary' : 'cta-band__btn cta-band__btn--secondary';
        ?>
        <a href="<?= htmlspecialchars($action['href'], ENT_QUOTES, 'UTF-8') ?>" class="<?= $cls ?>"><?= htmlspecialchars($action['text'], ENT_QUOTES, 'UTF-8') ?></a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>
