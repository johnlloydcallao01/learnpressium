/**
 * Learnpressium Enrollment Frontend JavaScript
 */

(function($) {
    'use strict';

    var EnrollmentFrontend = {
        
        init: function() {
            this.initCountdownTimers();
            this.bindEvents();
            this.startCountdownUpdates();
        },

        bindEvents: function() {
            // Refresh countdown timers every minute
            setInterval(function() {
                EnrollmentFrontend.updateCountdownTimers();
            }, 60000); // Update every minute
        },

        initCountdownTimers: function() {
            $('.schedule-countdown').each(function() {
                var $countdown = $(this);
                var targetDate = $countdown.data('target');
                
                if (targetDate) {
                    EnrollmentFrontend.updateSingleCountdown($countdown, targetDate);
                }
            });
        },

        updateCountdownTimers: function() {
            $('.schedule-countdown').each(function() {
                var $countdown = $(this);
                var targetDate = $countdown.data('target');
                
                if (targetDate) {
                    EnrollmentFrontend.updateSingleCountdown($countdown, targetDate);
                }
            });
        },

        updateSingleCountdown: function($countdown, targetDate) {
            var now = new Date().getTime();
            var target = new Date(targetDate).getTime();
            var difference = target - now;

            var $timer = $countdown.find('.countdown-timer');
            
            if (difference <= 0) {
                $timer.text('Available now');
                $timer.addClass('available');
                
                // Optionally reload the page to update access
                setTimeout(function() {
                    if (confirm('This course is now available! Would you like to refresh the page?')) {
                        location.reload();
                    }
                }, 2000);
                
                return;
            }

            var days = Math.floor(difference / (1000 * 60 * 60 * 24));
            var hours = Math.floor((difference % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            var minutes = Math.floor((difference % (1000 * 60 * 60)) / (1000 * 60));
            var seconds = Math.floor((difference % (1000 * 60)) / 1000);

            var timeString = this.formatTimeString(days, hours, minutes, seconds);
            $timer.text(timeString);

            // Add urgent class if less than 1 hour remaining
            if (difference < (1000 * 60 * 60)) {
                $timer.addClass('urgent');
            } else {
                $timer.removeClass('urgent');
            }
        },

        formatTimeString: function(days, hours, minutes, seconds) {
            var parts = [];

            if (days > 0) {
                parts.push(days + (days === 1 ? ' day' : ' days'));
            }

            if (hours > 0) {
                parts.push(hours + (hours === 1 ? ' hour' : ' hours'));
            }

            if (minutes > 0 && days === 0) { // Only show minutes if less than a day
                parts.push(minutes + (minutes === 1 ? ' minute' : ' minutes'));
            }

            if (parts.length === 0 || (days === 0 && hours === 0)) {
                // Show seconds only if very close
                if (days === 0 && hours === 0 && minutes < 5) {
                    parts = [minutes + (minutes === 1 ? ' minute' : ' minutes')];
                    if (minutes === 0) {
                        parts = [seconds + (seconds === 1 ? ' second' : ' seconds')];
                    }
                }
            }

            if (parts.length === 0) {
                return 'Less than a minute';
            }

            return parts.join(', ');
        },

        startCountdownUpdates: function() {
            // Update countdown timers every second for more precision
            setInterval(function() {
                $('.countdown-timer.urgent').each(function() {
                    var $timer = $(this);
                    var $countdown = $timer.closest('.schedule-countdown');
                    var targetDate = $countdown.data('target');
                    
                    if (targetDate) {
                        EnrollmentFrontend.updateSingleCountdown($countdown, targetDate);
                    }
                });
            }, 1000); // Update every second for urgent timers
        },

        // Utility function to show notifications
        showNotification: function(message, type) {
            type = type || 'info';
            
            var $notification = $('<div class="learnpressium-notification notification-' + type + '">' + message + '</div>');
            
            $('body').append($notification);
            
            $notification.fadeIn(300);
            
            setTimeout(function() {
                $notification.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        },

        // Function to handle course access attempts
        handleCourseAccess: function(courseId) {
            // This could be used to show additional information
            // when users try to access scheduled courses
            console.log('Course access attempted for course ID:', courseId);
        },

        // Function to refresh course status
        refreshCourseStatus: function(courseId) {
            // This could make an AJAX call to check if course access has changed
            $.ajax({
                url: learnpressium_enrollment_frontend.ajax_url,
                type: 'POST',
                data: {
                    action: 'learnpressium_check_course_access',
                    course_id: courseId,
                    nonce: learnpressium_enrollment_frontend.nonce
                },
                success: function(response) {
                    if (response.success && response.data.access_changed) {
                        EnrollmentFrontend.showNotification(
                            'Course access status has been updated!', 
                            'success'
                        );
                        
                        // Optionally reload the page
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    }
                }
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        EnrollmentFrontend.init();
    });

    // Make EnrollmentFrontend globally available
    window.LearnpressiumEnrollmentFrontend = EnrollmentFrontend;

})(jQuery);

// CSS for notifications (injected via JavaScript)
jQuery(document).ready(function($) {
    if (!$('#learnpressium-notification-styles').length) {
        var notificationStyles = `
            <style id="learnpressium-notification-styles">
                .learnpressium-notification {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    z-index: 999999;
                    padding: 15px 20px;
                    border-radius: 4px;
                    color: #fff;
                    font-weight: 600;
                    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
                    display: none;
                    max-width: 300px;
                    word-wrap: break-word;
                }
                
                .learnpressium-notification.notification-success {
                    background-color: #46b450;
                }
                
                .learnpressium-notification.notification-error {
                    background-color: #dc3232;
                }
                
                .learnpressium-notification.notification-warning {
                    background-color: #ffb900;
                    color: #333;
                }
                
                .learnpressium-notification.notification-info {
                    background-color: #0073aa;
                }
                
                @media screen and (max-width: 480px) {
                    .learnpressium-notification {
                        top: 10px;
                        right: 10px;
                        left: 10px;
                        max-width: none;
                    }
                }
            </style>
        `;
        
        $('head').append(notificationStyles);
    }
});
