/**
 * Fynla — learn/guide/enjoying-your-wealth page interactions
 */
(function () {
  'use strict';

  var CURRENT_PATH = '/learn/guide/enjoying-your-wealth';

  var ALL_GUIDES = [
    { title: 'What is an ISA?',                              href: '/learn/what-is-an-isa',                 category: 'explainers', color: '#E83E6D' },
    { title: 'What is Drawdown?',                            href: '/learn/what-is-drawdown',               category: 'explainers', color: '#E83E6D' },
    { title: 'What is Salary Sacrifice?',                    href: '/learn/what-is-salary-sacrifice',       category: 'explainers', color: '#E83E6D' },
    { title: 'What is a Lasting Power of Attorney?',         href: '/learn/what-is-an-lpa',                 category: 'explainers', color: '#E83E6D' },
    { title: 'What is a Self-Invested Personal Pension?',    href: '/learn/what-is-a-sipp',                 category: 'explainers', color: '#E83E6D' },
    { title: 'What is Inheritance Tax?',                     href: '/learn/what-is-inheritance-tax',        category: 'explainers', color: '#E83E6D' },
    { title: 'What is Financial Planning?',                  href: '/learn',                                category: 'explainers', color: '#E83E6D' },
    { title: 'Should I overpay my mortgage?',                href: '/learn/should-i-overpay-my-mortgage',   category: 'decisions',  color: '#20B486' },
    { title: 'Should I consolidate my pensions?',            href: '/learn/should-i-consolidate-pensions',  category: 'decisions',  color: '#20B486' },
    { title: 'When should I make a will?',                   href: '/learn/when-should-i-make-a-will',      category: 'decisions',  color: '#20B486' },
    { title: 'Lifetime ISA or ISA?',                         href: '/learn/should-i-use-a-lisa-or-isa',     category: 'decisions',  color: '#20B486' },
    { title: 'When can I afford to retire?',                 href: '/learn/when-can-i-afford-to-retire',    category: 'decisions',  color: '#20B486' },
    { title: 'Starting Out: money basics',                   href: '/learn/guide/starting-out',             category: 'stages',     color: '#5854E6' },
    { title: 'Building Foundations: first home',             href: '/learn/guide/building-foundations',     category: 'stages',     color: '#5854E6' },
    { title: 'Protecting and Growing: family finances',      href: '/learn/guide/protecting-and-growing',   category: 'stages',     color: '#5854E6' },
    { title: 'Planning Your Future: retirement',             href: '/learn/guide/planning-your-future',     category: 'stages',     color: '#5854E6' },
    { title: 'Enjoying Your Wealth: estate planning',        href: '/learn/guide/enjoying-your-wealth',     category: 'stages',     color: '#5854E6' },
    { title: 'ISA allowance guide',                          href: '/learn/tax/isa-allowance',              category: 'tax',        color: '#6C83BC' },
    { title: 'Pension annual allowance',                     href: '/learn/tax/pension-annual-allowance',   category: 'tax',        color: '#6C83BC' },
    { title: 'Inheritance Tax thresholds',                   href: '/learn/tax/iht-thresholds',             category: 'tax',        color: '#6C83BC' },
    { title: 'Capital gains tax rates',                      href: '/learn/tax/capital-gains-tax',          category: 'tax',        color: '#6C83BC' },
    { title: 'Tax year checklist',                           href: '/learn/tax/tax-year-checklist',         category: 'tax',        color: '#6C83BC' },
  ];

  document.addEventListener('DOMContentLoaded', function () {
    wireGuideTabs();
  });

  function wireGuideTabs() {
    var tabButtons = document.querySelectorAll('.guide-cat-tab[data-category]');
    var linksContainer = document.getElementById('guide-links-container');
    if (!tabButtons.length || !linksContainer) return;

    var currentGuide = ALL_GUIDES.find(function (g) { return g.href === CURRENT_PATH; });
    var activeCategory = currentGuide ? currentGuide.category : 'all';

    tabButtons.forEach(function (btn) {
      if (btn.getAttribute('data-category') === activeCategory) { btn.classList.add('is-active'); }
    });
    renderLinks(linksContainer, activeCategory);

    tabButtons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var cat = btn.getAttribute('data-category');
        tabButtons.forEach(function (b) { b.classList.remove('is-active'); });
        btn.classList.add('is-active');
        activeCategory = cat;
        renderLinks(linksContainer, cat);
      });
    });
  }

  function renderLinks(container, category) {
    var filtered = category === 'all' ? ALL_GUIDES : ALL_GUIDES.filter(function (g) { return g.category === category; });
    container.innerHTML = '';
    filtered.forEach(function (guide) {
      var isCurrent = guide.href === CURRENT_PATH;
      var a = document.createElement('a');
      a.href = guide.href;
      a.className = 'guide-link' + (isCurrent ? ' is-current' : '');
      var dot = document.createElement('span');
      dot.className = 'guide-link__dot';
      dot.style.background = isCurrent ? '#FFFFFF' : guide.color;
      a.appendChild(dot);
      a.appendChild(document.createTextNode(guide.title));
      container.appendChild(a);
    });
  }

}());
