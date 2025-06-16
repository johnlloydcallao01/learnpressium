/**
 * Learnpressium Enrollment Admin JavaScript
 */

(function($) {
    'use strict';

    var EnrollmentAdmin = {
        
        init: function() {
            this.bindEvents();
            this.initDatepickers();
            this.loadInitialData();
        },

        bindEvents: function() {
            // Modal events
            $(document).on('click', '#add-new-schedule, #add-course-schedule, #add-user-schedule', this.openScheduleModal);
            $(document).on('click', '.learnpressium-modal-close, #cancel-schedule', this.closeScheduleModal);
            $(document).on('click', '#save-schedule', this.saveSchedule);
            
            // Table events
            $(document).on('click', '.edit-schedule', this.editSchedule);
            $(document).on('click', '.delete-schedule', this.deleteSchedule);
            $(document).on('click', '#filter-schedules', this.filterSchedules);
            
            // Tools integration events
            $(document).on('submit', '#lp-schedule-enrollment-form', this.handleToolsScheduling);
            $(document).on('click', '#filter-bulk-schedules', this.filterBulkSchedules);
            $(document).on('click', '#apply-bulk-action', this.applyBulkAction);
            $(document).on('change', '#select-all-schedules', this.toggleAllSchedules);
            
            // Close modal when clicking outside
            $(document).on('click', '.learnpressium-modal', function(e) {
                if (e.target === this) {
                    EnrollmentAdmin.closeScheduleModal();
                }
            });
        },

        initDatepickers: function() {
            $('.datepicker').datepicker({
                dateFormat: 'yy-mm-dd',
                minDate: 0,
                changeMonth: true,
                changeYear: true
            });
        },

        loadInitialData: function() {
            // Load schedules for main admin page
            if ($('#schedules-table-body').length) {
                this.loadSchedules();
            }
            
            // Load course schedules for course edit page
            if ($('#course-schedules-tbody').length) {
                var courseId = $('#add-course-schedule').data('course-id');
                if (courseId) {
                    this.loadCourseSchedules(courseId);
                }
            }
            
            // Load user schedules for user profile page
            if ($('#user-schedules-tbody').length) {
                var userId = $('#add-user-schedule').data('user-id');
                if (userId) {
                    this.loadUserSchedules(userId);
                }
            }
            
            // Load data for tools page
            if ($('#learnpressium-scheduled-enrollment-tools').length) {
                this.loadToolsData();
            }
        },

        openScheduleModal: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var courseId = $button.data('course-id');
            var userId = $button.data('user-id');
            
            // Reset form
            $('#schedule-form')[0].reset();
            $('#schedule-id').val('');
            $('#modal-title').text(learnpressium_enrollment.strings.add_new_schedule || 'Add New Schedule');
            
            // Pre-populate if course or user is specified
            if (courseId) {
                $('#course-select').val(courseId);
            }
            if (userId) {
                $('#user-select').val(userId);
            }
            
            // Load users and courses
            EnrollmentAdmin.loadUsersAndCourses();
            
            $('#schedule-modal').show();
        },

        closeScheduleModal: function() {
            $('#schedule-modal').hide();
        },

        loadUsersAndCourses: function() {
            // Load courses
            $.get(learnpressium_enrollment.ajax_url, {
                action: 'wp_ajax_nopriv_get_courses',
                nonce: learnpressium_enrollment.nonce
            }).done(function(response) {
                if (response.success) {
                    var $courseSelect = $('#course-select');
                    $courseSelect.empty().append('<option value="">Select Course</option>');
                    
                    $.each(response.data, function(index, course) {
                        $courseSelect.append('<option value="' + course.value + '">' + course.text + '</option>');
                    });
                }
            });
            
            // Load users
            $.get(learnpressium_enrollment.ajax_url, {
                action: 'wp_ajax_nopriv_get_users',
                nonce: learnpressium_enrollment.nonce
            }).done(function(response) {
                if (response.success) {
                    var $userSelect = $('#user-select');
                    $userSelect.empty().append('<option value="">Select User</option>');
                    
                    $.each(response.data, function(index, user) {
                        $userSelect.append('<option value="' + user.value + '">' + user.text + '</option>');
                    });
                }
            });
        },

        saveSchedule: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $form = $('#schedule-form');
            var formData = $form.serializeArray();
            var data = {};
            
            // Convert form data to object
            $.each(formData, function(index, field) {
                data[field.name] = field.value;
            });
            
            // Combine date and time
            if (data.start_date && data.start_time) {
                data.start_date = data.start_date + ' ' + data.start_time + ':00';
            }
            if (data.end_date && data.end_time) {
                data.end_date = data.end_date + ' ' + data.end_time + ':00';
            }
            
            data.action = 'learnpressium_schedule_enrollment';
            data.nonce = learnpressium_enrollment.nonce;
            
            $button.prop('disabled', true).text(learnpressium_enrollment.strings.loading || 'Loading...');
            
            $.post(learnpressium_enrollment.ajax_url, data)
                .done(function(response) {
                    if (response.success) {
                        EnrollmentAdmin.showMessage(learnpressium_enrollment.strings.schedule_saved || 'Schedule saved successfully', 'success');
                        EnrollmentAdmin.closeScheduleModal();
                        EnrollmentAdmin.refreshCurrentView();
                    } else {
                        EnrollmentAdmin.showMessage(response.data || 'Error occurred', 'error');
                    }
                })
                .fail(function() {
                    EnrollmentAdmin.showMessage(learnpressium_enrollment.strings.error_occurred || 'An error occurred', 'error');
                })
                .always(function() {
                    $button.prop('disabled', false).text('Save Schedule');
                });
        },

        editSchedule: function(e) {
            e.preventDefault();
            
            var scheduleId = $(this).data('schedule-id');
            
            // Load schedule data and populate modal
            $.get(learnpressium_enrollment.ajax_url, {
                action: 'learnpressium_get_schedule',
                schedule_id: scheduleId,
                nonce: learnpressium_enrollment.nonce
            }).done(function(response) {
                if (response.success) {
                    var schedule = response.data;
                    
                    $('#schedule-id').val(schedule.schedule_id);
                    $('#user-select').val(schedule.user_id);
                    $('#course-select').val(schedule.course_id);
                    
                    // Split datetime
                    var startParts = schedule.scheduled_start_date.split(' ');
                    $('#start-date').val(startParts[0]);
                    $('#start-time').val(startParts[1].substring(0, 5));
                    
                    if (schedule.scheduled_end_date) {
                        var endParts = schedule.scheduled_end_date.split(' ');
                        $('#end-date').val(endParts[0]);
                        $('#end-time').val(endParts[1].substring(0, 5));
                    }
                    
                    $('#schedule-notes').val(schedule.notes);
                    $('#modal-title').text('Edit Schedule');
                    
                    EnrollmentAdmin.loadUsersAndCourses();
                    $('#schedule-modal').show();
                }
            });
        },

        deleteSchedule: function(e) {
            e.preventDefault();
            
            if (!confirm(learnpressium_enrollment.strings.confirm_delete || 'Are you sure?')) {
                return;
            }
            
            var scheduleId = $(this).data('schedule-id');
            
            $.post(learnpressium_enrollment.ajax_url, {
                action: 'learnpressium_delete_schedule',
                schedule_id: scheduleId,
                nonce: learnpressium_enrollment.nonce
            }).done(function(response) {
                if (response.success) {
                    EnrollmentAdmin.showMessage(learnpressium_enrollment.strings.schedule_deleted || 'Schedule deleted successfully', 'success');
                    EnrollmentAdmin.refreshCurrentView();
                } else {
                    EnrollmentAdmin.showMessage(response.data || 'Error occurred', 'error');
                }
            });
        },

        loadSchedules: function() {
            var status = $('#filter-by-status').val();
            
            $.get(learnpressium_enrollment.ajax_url, {
                action: 'learnpressium_get_schedules',
                status: status,
                nonce: learnpressium_enrollment.nonce
            }).done(function(response) {
                if (response.success) {
                    EnrollmentAdmin.renderSchedulesTable(response.data, '#schedules-table-body');
                }
            });
        },

        loadCourseSchedules: function(courseId) {
            $.get(learnpressium_enrollment.ajax_url, {
                action: 'learnpressium_get_schedules',
                course_id: courseId,
                nonce: learnpressium_enrollment.nonce
            }).done(function(response) {
                if (response.success) {
                    EnrollmentAdmin.renderSchedulesTable(response.data, '#course-schedules-tbody', false);
                }
            });
        },

        loadUserSchedules: function(userId) {
            $.get(learnpressium_enrollment.ajax_url, {
                action: 'learnpressium_get_schedules',
                user_id: userId,
                nonce: learnpressium_enrollment.nonce
            }).done(function(response) {
                if (response.success) {
                    EnrollmentAdmin.renderSchedulesTable(response.data, '#user-schedules-tbody', true);
                }
            });
        },

        renderSchedulesTable: function(schedules, tableSelector, showCourse) {
            var $tbody = $(tableSelector);
            $tbody.empty();
            
            if (schedules.length === 0) {
                var colspan = showCourse === false ? 5 : 6;
                $tbody.append('<tr><td colspan="' + colspan + '" class="text-center">No schedules found</td></tr>');
                return;
            }
            
            $.each(schedules, function(index, schedule) {
                var row = '<tr>';
                
                if (showCourse !== false) {
                    row += '<td>' + (schedule.user_name || 'Unknown User') + '</td>';
                }
                if (showCourse !== true) {
                    row += '<td>' + (schedule.course_title || 'Unknown Course') + '</td>';
                }
                
                row += '<td>' + EnrollmentAdmin.formatDateTime(schedule.scheduled_start_date) + '</td>';
                row += '<td>' + (schedule.scheduled_end_date ? EnrollmentAdmin.formatDateTime(schedule.scheduled_end_date) : 'No expiration') + '</td>';
                row += '<td><span class="status-' + schedule.status + '">' + schedule.status.charAt(0).toUpperCase() + schedule.status.slice(1) + '</span></td>';
                row += '<td>';
                row += '<button type="button" class="button button-small edit-schedule" data-schedule-id="' + schedule.schedule_id + '">Edit</button> ';
                row += '<button type="button" class="button button-small delete-schedule" data-schedule-id="' + schedule.schedule_id + '">Delete</button>';
                row += '</td>';
                row += '</tr>';
                
                $tbody.append(row);
            });
        },

        formatDateTime: function(datetime) {
            if (!datetime) return '';
            var date = new Date(datetime);
            return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        },

        filterSchedules: function() {
            EnrollmentAdmin.loadSchedules();
        },

        refreshCurrentView: function() {
            if ($('#schedules-table-body').length) {
                this.loadSchedules();
            }
            
            var courseId = $('#add-course-schedule').data('course-id');
            if (courseId) {
                this.loadCourseSchedules(courseId);
            }
            
            var userId = $('#add-user-schedule').data('user-id');
            if (userId) {
                this.loadUserSchedules(userId);
            }
            
            if ($('#bulk-schedules-tbody').length) {
                this.filterBulkSchedules();
            }
        },

        showMessage: function(message, type) {
            var className = type === 'success' ? 'notice-success' : 'notice-error';
            var $notice = $('<div class="notice ' + className + ' is-dismissible"><p>' + message + '</p></div>');
            
            $('.wrap h1').after($notice);
            
            setTimeout(function() {
                $notice.fadeOut();
            }, 3000);
        },

        // Tools integration methods
        loadToolsData: function() {
            this.loadToolsDropdowns();
            this.filterBulkSchedules();
        },

        loadToolsDropdowns: function() {
            // Load courses for tools
            $.get(wpApiSettings.root + 'learnpressium/v1/tools/courses', {
                headers: {
                    'X-WP-Nonce': wpApiSettings.nonce
                }
            }).done(function(courses) {
                var $select = $('#schedule-course-select');
                $select.empty();
                $.each(courses, function(index, course) {
                    $select.append('<option value="' + course.value + '">' + course.text + '</option>');
                });
            });
            
            // Load users for tools
            $.get(wpApiSettings.root + 'learnpressium/v1/tools/users', {
                headers: {
                    'X-WP-Nonce': wpApiSettings.nonce
                }
            }).done(function(users) {
                var $select = $('#schedule-user-select');
                $select.empty();
                $.each(users, function(index, user) {
                    $select.append('<option value="' + user.value + '">' + user.text + '</option>');
                });
            });
        },

        handleToolsScheduling: function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $button = $form.find('.lp-button-schedule-enrollment');
            var $message = $form.find('.message');
            var $percent = $form.find('.percent');
            
            var formData = $form.serializeArray();
            var data = {};
            
            $.each(formData, function(index, field) {
                if (data[field.name]) {
                    if (!Array.isArray(data[field.name])) {
                        data[field.name] = [data[field.name]];
                    }
                    data[field.name].push(field.value);
                } else {
                    data[field.name] = field.value;
                }
            });
            
            data.action = 'learnpressium_tools_schedule_assignment';
            data.nonce = learnpressium_enrollment.nonce;
            
            $button.prop('disabled', true);
            $message.text('Processing...').css('color', 'blue');
            
            $.post(learnpressium_enrollment.ajax_url, data)
                .done(function(response) {
                    if (response.success) {
                        $message.text(response.data.message).css('color', 'green');
                        $form[0].reset();
                        EnrollmentAdmin.filterBulkSchedules();
                    } else {
                        $message.text(response.data.message || 'Error occurred').css('color', 'red');
                    }
                })
                .fail(function() {
                    $message.text('An error occurred').css('color', 'red');
                })
                .always(function() {
                    $button.prop('disabled', false);
                    setTimeout(function() {
                        $message.text('');
                        $percent.text('');
                    }, 3000);
                });
        },

        filterBulkSchedules: function() {
            var status = $('#bulk-status-filter').val();
            
            $.get(wpApiSettings.root + 'learnpressium/v1/tools/schedules', {
                status: status,
                headers: {
                    'X-WP-Nonce': wpApiSettings.nonce
                }
            }).done(function(schedules) {
                EnrollmentAdmin.renderBulkSchedulesTable(schedules);
            });
        },

        renderBulkSchedulesTable: function(schedules) {
            var $tbody = $('#bulk-schedules-tbody');
            $tbody.empty();
            
            if (schedules.length === 0) {
                $tbody.append('<tr><td colspan="7" class="text-center">No schedules found</td></tr>');
                return;
            }
            
            $.each(schedules, function(index, schedule) {
                var row = '<tr>';
                row += '<td><input type="checkbox" class="schedule-checkbox" value="' + schedule.schedule_id + '"></td>';
                row += '<td>' + (schedule.user_name || 'Unknown User') + '</td>';
                row += '<td>' + (schedule.course_title || 'Unknown Course') + '</td>';
                row += '<td>' + EnrollmentAdmin.formatDateTime(schedule.scheduled_start_date) + '</td>';
                row += '<td>' + (schedule.scheduled_end_date ? EnrollmentAdmin.formatDateTime(schedule.scheduled_end_date) : 'No expiration') + '</td>';
                row += '<td><span class="status-' + schedule.status + '">' + schedule.status.charAt(0).toUpperCase() + schedule.status.slice(1) + '</span></td>';
                row += '<td>';
                row += '<button type="button" class="button button-small edit-schedule" data-schedule-id="' + schedule.schedule_id + '">Edit</button> ';
                row += '<button type="button" class="button button-small delete-schedule" data-schedule-id="' + schedule.schedule_id + '">Delete</button>';
                row += '</td>';
                row += '</tr>';
                
                $tbody.append(row);
            });
        },

        toggleAllSchedules: function() {
            var checked = $(this).prop('checked');
            $('.schedule-checkbox').prop('checked', checked);
        },

        applyBulkAction: function() {
            var action = $('#bulk-actions').val();
            if (!action) return;
            
            var selectedIds = $('.schedule-checkbox:checked').map(function() {
                return $(this).val();
            }).get();
            
            if (selectedIds.length === 0) {
                alert('Please select at least one schedule');
                return;
            }
            
            if (!confirm('Are you sure you want to apply this action to ' + selectedIds.length + ' schedule(s)?')) {
                return;
            }
            
            // Handle bulk actions
            $.post(learnpressium_enrollment.ajax_url, {
                action: 'learnpressium_bulk_schedule_action',
                bulk_action: action,
                schedule_ids: selectedIds,
                nonce: learnpressium_enrollment.nonce
            }).done(function(response) {
                if (response.success) {
                    EnrollmentAdmin.showMessage('Bulk action completed successfully', 'success');
                    EnrollmentAdmin.filterBulkSchedules();
                } else {
                    EnrollmentAdmin.showMessage(response.data || 'Error occurred', 'error');
                }
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        EnrollmentAdmin.init();
    });

})(jQuery);
