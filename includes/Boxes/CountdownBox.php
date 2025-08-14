<?php
/**
 * Countdown Box Class
 * 
 * Displays countdown timer until course launch
 */

namespace CourseBoxManager\Boxes;

class CountdownBox extends AbstractBox {
    
    public function should_display() {
        // Only display when explicitly set to countdown state
        return $this->box_state === 'countdown' && $this->show_countdown && $this->launch_date;
    }
    
    protected function get_box_classes() {
        return parent::get_box_classes() . ' course-launch';
    }
    
    public function render() {
        ob_start();
        ?>
        <div class="<?php echo esc_attr($this->get_box_classes()); ?>">
            <div class="countdown">
                <span>COURSE LAUNCH IN:</span>
                <div class="countdown-timer" 
                     id="countdown-timer-<?php echo esc_attr($this->course_id); ?>" 
                     data-launch-date="<?php echo esc_attr($this->launch_date); ?>">
                    <?php echo $this->render_countdown_timer(); ?>
                </div>
            </div>
            <h3>Join Waitlist for Free</h3>
            <p class="description">Gain access to live streams, free credits for Arcana, and more.</p>
            <?php echo do_shortcode('[contact-form-7 id="255b390" title="Course Launch"]'); ?>
            <p class="terms">By signing up, you agree to the Terms & Conditions.</p>
        </div>
        <?php
        return ob_get_clean();
    }
    
    protected function render_countdown_timer() {
        $time_diff = strtotime($this->launch_date) - current_time('timestamp');
        
        if ($time_diff <= 0) {
            return '<span class="launch-soon">Launched!</span>';
        }
        
        $days = floor($time_diff / (60 * 60 * 24));
        $hours = floor(($time_diff % (60 * 60 * 24)) / (60 * 60));
        $minutes = floor(($time_diff % (60 * 60)) / 60);
        $seconds = $time_diff % 60;
        
        ob_start();
        ?>
        <div class="time-unit" data-unit="days">
            <span class="time-value"><?php echo esc_html(sprintf('%02d', $days)); ?></span>
            <span class="time-label">days</span>
        </div>
        <div class="time-unit" data-unit="hours">
            <span class="time-value"><?php echo esc_html(sprintf('%02d', $hours)); ?></span>
            <span class="time-label">hrs</span>
        </div>
        <div class="time-unit" data-unit="minutes">
            <span class="time-value"><?php echo esc_html(sprintf('%02d', $minutes)); ?></span>
            <span class="time-label">min</span>
        </div>
        <div class="time-unit" data-unit="seconds">
            <span class="time-value"><?php echo esc_html(sprintf('%02d', $seconds)); ?></span>
            <span class="time-label">sec</span>
        </div>
        <?php
        return ob_get_clean();
    }
}