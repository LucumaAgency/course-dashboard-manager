<?php
/**
 * Buy Course Box Class
 * 
 * Displays direct purchase option for courses
 */

namespace CourseBoxManager\Boxes;

class BuyCourseBox extends AbstractBox {
    
    public function should_display() {
        return $this->box_state === 'buy-course' && 
               !$this->is_out_of_stock && 
               !$this->show_countdown;
    }
    
    protected function get_box_classes() {
        $classes = parent::get_box_classes() . ' buy-course';
        if (!$this->is_group_course) {
            $classes .= ' selected';
        }
        return $classes;
    }
    
    public function render() {
        // Get custom text or use default
        $custom_text = $this->process_custom_text('buy', [
            'price' => $this->format_price($this->course_price),
            'button' => $this->render_add_to_cart_button('Buy Course')
        ]);
        
        ob_start();
        ?>
        <div class="<?php echo esc_attr($this->get_box_classes()); ?>" 
             data-course-id="<?php echo esc_attr($this->course_id); ?>" 
             onclick="selectBox(this, 'box1', <?php echo esc_attr($this->course_id); ?>)">
            
            <?php if (empty($custom_text)) : ?>
                <div class="statebox">
                    <?php echo $this->render_selection_indicator(); ?>
                    <div>
                        <h3>Buy This Course</h3>
                        <p class="price"><?php echo esc_html($this->format_price($this->course_price)); ?></p>
                        <p class="description">Pay once, own the course forever.</p>
                    </div>
                </div>
                <?php echo $this->render_add_to_cart_button('Buy Course'); ?>
            <?php else : ?>
                <?php echo $this->render_selection_indicator(); ?>
                <div class="box-content">
                    <?php echo $custom_text; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    protected function render_selection_indicator() {
        ob_start();
        ?>
        <div class="circlecontainer" style="display: <?php echo $this->is_group_course ? 'none' : 'flex'; ?>;">
            <div class="outer-circle">
                <div class="middle-circle">
                    <div class="inner-circle"></div>
                </div>
            </div>
        </div>
        <div class="circle-container" style="display: <?php echo $this->is_group_course ? 'flex' : 'none'; ?>;">
            <div class="circle"></div>
        </div>
        <?php
        return ob_get_clean();
    }
}