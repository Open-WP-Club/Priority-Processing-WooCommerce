<?php

/**
 * Admin Dashboard Handler
 * Renders the statistics overview shown in the WooCommerce settings tab
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Dashboard
{
  private $statistics;
  private $settings_handler;

  public function __construct( $statistics_instance, Admin_Settings $settings_handler ) {
    $this->statistics       = $statistics_instance;
    $this->settings_handler = $settings_handler;
  }

  /**
   * Render the statistics overview (used by the Statistics section of the settings tab)
   */
  public function render_statistics_panel()
  {
    $stats      = $this->statistics->get_statistics();
    $cache_info = $this->statistics->get_cache_info();
?>
    <div class="wpp-stats-panel">
      <div class="wpp-stats-header">
        <span></span>
        <div class="wpp-stats-controls">
          <span class="wpp-cache-info">
            <?php if ($cache_info['is_cached']): ?>
              <small><?php printf(__('Cached for %d hours', 'woo-priority'), $cache_info['cache_duration_hours']); ?></small>
            <?php else: ?>
              <small><?php _e('Live data', 'woo-priority'); ?></small>
            <?php endif; ?>
          </span>
          <button type="button" id="wpp-refresh-stats" class="button button-secondary">
            <span class="dashicons dashicons-update"></span>
            <?php _e('Refresh Stats', 'woo-priority'); ?>
          </button>
        </div>
      </div>

      <div class="wpp-statistics-grid" id="wpp-statistics-container">
        <?php $this->render_statistics_cards($stats); ?>
      </div>

      <p class="description"><?php _e('Statistics are cached for 24 hours to improve performance. Click "Refresh Stats" to get the latest data.', 'woo-priority'); ?></p>
    </div>
<?php
  }

  /**
   * Render individual statistics cards
   */
  private function render_statistics_cards($stats)
  {
    $cards_data = [
      [
        'icon' => '⚡',
        'value' => number_format($stats['total_priority_orders']),
        'label' => __('Total Priority Orders', 'woo-priority'),
        'id' => 'stat-total-orders'
      ],
      [
        'icon' => '💰',
        'value' => wc_price($stats['total_priority_revenue']),
        'label' => __('Total Priority Revenue', 'woo-priority'),
        'id' => 'stat-total-revenue'
      ],
      [
        'icon' => '📈',
        'value' => $stats['priority_percentage'] . '%',
        'label' => __('Priority Rate', 'woo-priority'),
        'id' => 'stat-percentage'
      ],
      [
        'icon' => '💵',
        'value' => wc_price($stats['average_priority_fee']),
        'label' => __('Average Fee', 'woo-priority'),
        'id' => 'stat-avg-fee'
      ],
      [
        'icon' => '📅',
        'value' => number_format($stats['today_priority_orders']),
        'label' => __('Today', 'woo-priority'),
        'id' => 'stat-today'
      ],
      [
        'icon' => '📊',
        'value' => number_format($stats['this_week_priority_orders']),
        'label' => __('This Week', 'woo-priority'),
        'id' => 'stat-this-week'
      ],
      [
        'icon' => '📆',
        'value' => number_format($stats['this_month_priority_orders']),
        'label' => __('This Month', 'woo-priority'),
        'id' => 'stat-this-month'
      ],
      [
        'icon' => '🔄',
        'value' => esc_html(gmdate('H:i', strtotime($stats['last_updated']))),
        'label' => __('Last Updated', 'woo-priority'),
        'id' => 'stat-last-updated'
      ]
    ];

    foreach ($cards_data as $card) {
      echo '<div class="wpp-stat-card">';
      echo '<div class="wpp-stat-icon">' . $card['icon'] . '</div>';
      echo '<div class="wpp-stat-content">';
      echo '<div class="wpp-stat-value" id="' . $card['id'] . '">' . $card['value'] . '</div>';
      echo '<div class="wpp-stat-label">' . $card['label'] . '</div>';
      echo '</div>';
      echo '</div>';
    }
  }

}
