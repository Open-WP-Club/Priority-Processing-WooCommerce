<?php

/**
 * Admin Dashboard Handler
 * Manages the main admin page display and statistics dashboard
 */
class Admin_Dashboard
{
  private $statistics;
  private $settings_handler;

  public function __construct( $statistics_instance, Admin_Settings $settings_handler ) {
    $this->statistics       = $statistics_instance;
    $this->settings_handler = $settings_handler;
  }

  /**
   * Display the main admin page
   */
  public function display_page()
  {
    $settings   = $this->settings_handler->get_settings();
    $stats      = $this->statistics->get_statistics();
    $cache_info = $this->statistics->get_cache_info();

    $this->render_page_header();
    $this->render_statistics_section($stats, $cache_info);
    $this->render_settings_section($settings);
  }

  /**
   * Render page header
   */
  private function render_page_header()
  {
?>
    <div class="wrap wpp-admin-container">
      <h1><?php _e('Priority Processing Settings', 'woo-priority'); ?></h1>

      <?php if (isset($_GET['settings-updated'])): ?>
        <div class="notice notice-success is-dismissible">
          <p><strong><?php _e('Settings saved successfully!', 'woo-priority'); ?></strong> <?php _e('Your priority processing options are now active.', 'woo-priority'); ?></p>
        </div>
      <?php endif; ?>
    <?php
  }

  /**
   * Render statistics section
   */
  private function render_statistics_section($stats, $cache_info)
  {
    ?>
      <!-- Statistics Section -->
      <div class="wpp-statistics-section">
        <div class="wpp-statistics-header">
          <h2><?php _e('Priority Processing Statistics', 'woo-priority'); ?></h2>
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

        <div class="wpp-statistics-note">
          <p><strong><?php _e('Note:', 'woo-priority'); ?></strong> <?php _e('Statistics are cached for 24 hours to improve performance. Click "Refresh Stats" to get the latest data.', 'woo-priority'); ?></p>
        </div>
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

  /**
   * Render settings section
   */
  private function render_settings_section($settings)
  {
    ?>
      <div class="wpp-settings-grid">
        <!-- Main Settings Panel -->
        <div class="wpp-settings-card">
          <h2><?php _e('Configuration', 'woo-priority'); ?></h2>

          <form method="post" action="options.php" id="wpp-settings-form">
            <?php settings_fields('wpp_settings'); ?>

            <?php
            // Render settings sections
            $this->settings_handler->render_basic_settings($settings);
            $this->settings_handler->render_permissions_settings($settings);
            $this->settings_handler->render_display_settings($settings);
            ?>

            <?php submit_button(__('Save Changes', 'woo-priority'), 'primary', 'submit', false); ?>
          </form>
        </div>

        <!-- Preview Panel -->
        <?php $this->settings_handler->render_preview_panel($settings); ?>
      </div>
    </div>
  <?php
  }

}
