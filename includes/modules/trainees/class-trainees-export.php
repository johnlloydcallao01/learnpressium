<?php

/**
 * The Trainees Export Functionality
 *
 * @package    Learnpressium
 * @subpackage Learnpressium/includes/modules/trainees
 */

/**
 * The Trainees Export class.
 *
 * Handles Excel export functionality for trainees data.
 */
class Trainees_Export {

    /**
     * Enqueue SheetJS library for Excel export
     */
    public function enqueue_scripts($hook) {
        if ('toplevel_page_trainees' !== $hook) {
            return;
        }

        // Enqueue SheetJS library
        wp_enqueue_script('sheetjs', 'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js', array(), '0.18.5', true);
    }

    /**
     * Add JavaScript for Excel export functionality
     */
    public function add_export_script() {
        // Only add the script on the trainees page
        $screen = get_current_screen();
        if ($screen->id !== 'toplevel_page_trainees') {
            return;
        }
        ?>
        <script>
            jQuery(document).ready(function($) {
        // Handle the export button click
        $('#export-trainees').on('click', function() {
            exportTraineesToExcel();
        });

        /**
         * Main function to export trainees data to Excel
         */
        function exportTraineesToExcel() {
            // Get the table element
            const table = document.getElementById('trainees-table');
            if (!table) {
                console.error('Trainees table not found');
                return;
            }

            // Get the current date for the title
            const today = new Date();
            const dateString = today.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            const reportTitle = "Trainees Report (" + dateString + ")";

            // Create workbook and worksheet
            const wb = XLSX.utils.book_new();
            wb.Props = {
                Title: reportTitle,
                Subject: "Trainees Data",
                Author: "WordPress",
                CreatedDate: today
            };

            // Get headers from the table
            const headers = [];
            const headerCells = table.querySelectorAll('thead th');
            headerCells.forEach(cell => {
                headers.push(cell.textContent.trim());
            });

            // Create a sparse worksheet instead of using aoa_to_sheet
            const ws = {};
            const range = { s: { c: 0, r: 0 }, e: { c: 9, r: 0 } }; // Updated to include 2 new columns

            // Helper function to set cell value and style
            function setCellValue(ws, row, col, value, style = {}) {
                const cell_ref = XLSX.utils.encode_cell({ r: row, c: col });
                ws[cell_ref] = {
                    v: value,  // value
                    t: typeof value === 'number' ? 'n' : 's', // type (string or number)
                    s: style   // style
                };
            }

            // Add title row (merged across all columns)
            setCellValue(ws, 0, 0, reportTitle.toUpperCase(), {
                font: {
                    bold: true,
                    sz: 24,
                    color: { rgb: "000000" }
                },
                alignment: {
                    horizontal: 'center',
                    vertical: 'center'
                },
                border: {
                    bottom: { style: "medium", color: { rgb: "000000" } }
                },
                fill: {
                    fgColor: { rgb: "EFEFEF" }
                }
            });

            // Add empty row
            setCellValue(ws, 1, 0, "");

            // Add header row
            const headerStyle = {
                font: {
                    bold: true,
                    sz: 14,
                    color: { rgb: "000000" }
                },
                alignment: {
                    horizontal: 'center',
                    vertical: 'center'
                },
                fill: {
                    fgColor: {rgb: "E6E6E6"}
                },
                border: {
                    top: { style: "thin", color: { rgb: "000000" } },
                    bottom: { style: "thin", color: { rgb: "000000" } }
                }
            };

            for (let c = 0; c < headers.length; c++) {
                setCellValue(ws, 2, c, headers[c], headerStyle);
            }

            // Process table rows
            const rows = table.querySelectorAll('tbody tr');
            let currentRow = 3; // Start after headers

            rows.forEach((row, rowIndex) => {
                // Track the starting row for this trainee
                const startRow = currentRow;

                // Get basic trainee data - updated for modern table structure
                const id = row.querySelector('.column-id .id-badge').textContent.trim();
                const name = row.querySelector('.column-name .trainee-name').textContent.trim();
                const username = row.querySelector('.column-username').textContent.trim();
                const email = row.querySelector('.column-email').textContent.trim();
                const enrolledStatus = row.querySelector('.column-enrolled .modern-badge').textContent.trim();
                const activeEnrolledStatus = row.querySelector('.column-active-enrolled .modern-badge').textContent.trim();
                const scheduledStatus = row.querySelector('.column-scheduled .modern-badge').textContent.trim();

                // Process enrolled courses - updated for modern table structure
                const coursesCell = row.querySelector('.column-enrolled-courses');
                const coursesList = coursesCell.querySelector('.courses-list');

                // Process active enrolled courses
                const activeCoursesCell = row.querySelector('.column-active-enrolled-courses');
                const activeCoursesList = activeCoursesCell.querySelector('.courses-list');

                // Process scheduled courses - NEW
                const scheduledCoursesCell = row.querySelector('.column-scheduled-courses');
                const scheduledCoursesList = scheduledCoursesCell.querySelector('.courses-list');

                // Determine how many rows we need for this trainee
                let courseCount = 0;
                let courses = [];
                let activeCoursesCount = 0;
                let activeCourses = [];
                let scheduledCoursesCount = 0;
                let scheduledCourses = [];

                if (coursesList) {
                    const courseItems = coursesList.querySelectorAll('.course-tag');
                    courseItems.forEach((item, index) => {
                        courses.push((index + 1) + '. ' + item.textContent.trim());
                    });
                    courseCount = courses.length;
                }

                if (activeCoursesList) {
                    const activeCourseItems = activeCoursesList.querySelectorAll('.course-tag');
                    activeCourseItems.forEach((item, index) => {
                        activeCourses.push((index + 1) + '. ' + item.textContent.trim());
                    });
                    activeCoursesCount = activeCourses.length;
                }

                if (scheduledCoursesList) {
                    const scheduledCourseItems = scheduledCoursesList.querySelectorAll('.course-tag');
                    scheduledCourseItems.forEach((item, index) => {
                        const title = item.textContent.trim();
                        const date = item.getAttribute('title') || '';
                        const courseText = date ? title + ' (' + date + ')' : title;
                        scheduledCourses.push((index + 1) + '. ' + courseText);
                    });
                    scheduledCoursesCount = scheduledCourses.length;
                }

                // We need at least one row even if there are no courses
                const rowsNeeded = Math.max(1, courseCount, activeCoursesCount, scheduledCoursesCount);

                // For each row needed for this trainee
                for (let i = 0; i < rowsNeeded; i++) {
                    const rowNum = startRow + i;

                    // Only add the trainee info in the first row
                    if (i === 0) {
                        setCellValue(ws, rowNum, 0, id);
                        setCellValue(ws, rowNum, 1, name);
                        setCellValue(ws, rowNum, 2, username);
                        setCellValue(ws, rowNum, 3, email);
                        setCellValue(ws, rowNum, 4, enrolledStatus);
                        setCellValue(ws, rowNum, 6, activeEnrolledStatus);
                        setCellValue(ws, rowNum, 8, scheduledStatus); // NEW: Has Scheduled Courses
                    }

                    // Add enrolled course in its own cell if available
                    if (i < courseCount) {
                        setCellValue(ws, rowNum, 5, courses[i], {
                            alignment: {
                                vertical: 'center',
                                horizontal: 'left'
                            }
                        });
                    } else if (i === 0 && courseCount === 0) {
                        // No courses case
                        setCellValue(ws, rowNum, 5, 'No enrolled courses', {
                            alignment: {
                                vertical: 'center',
                                horizontal: 'left'
                            },
                            font: {
                                italic: true,
                                color: { rgb: "999999" }
                            }
                        });
                    }

                    // Add active enrolled course in its own cell if available
                    if (i < activeCoursesCount) {
                        setCellValue(ws, rowNum, 7, activeCourses[i], {
                            alignment: {
                                vertical: 'center',
                                horizontal: 'left'
                            }
                        });
                    } else if (i === 0 && activeCoursesCount === 0) {
                        // No active courses case
                        setCellValue(ws, rowNum, 7, 'No active enrolled courses', {
                            alignment: {
                                vertical: 'center',
                                horizontal: 'left'
                            },
                            font: {
                                italic: true,
                                color: { rgb: "999999" }
                            }
                        });
                    }

                    // Add scheduled course in its own cell if available - NEW
                    if (i < scheduledCoursesCount) {
                        setCellValue(ws, rowNum, 9, scheduledCourses[i], {
                            alignment: {
                                vertical: 'center',
                                horizontal: 'left'
                            }
                        });
                    } else if (i === 0 && scheduledCoursesCount === 0) {
                        // No scheduled courses case
                        setCellValue(ws, rowNum, 9, 'No scheduled courses', {
                            alignment: {
                                vertical: 'center',
                                horizontal: 'left'
                            },
                            font: {
                                italic: true,
                                color: { rgb: "999999" }
                            }
                        });
                    }
                }

                // Update the current row for the next trainee
                currentRow += rowsNeeded;

                // Update the range to include all rows
                range.e.r = Math.max(range.e.r, currentRow - 1);
            });

            // Set the worksheet range
            ws['!ref'] = XLSX.utils.encode_range(range);

            // Set column widths
            const columnWidths = [
                {wch: 3},  // ID - reduced width
                {wch: 20}, // Name
                {wch: 15}, // Username
                {wch: 25}, // Email
                {wch: 10}, // Enrolled
                {wch: 50}, // Enrolled Courses
                {wch: 15}, // Has Active Enrolled Courses
                {wch: 50}, // Active Enrolled Courses
                {wch: 15}, // Has Scheduled Courses - NEW
                {wch: 50}  // Scheduled Courses - NEW
            ];
            ws['!cols'] = columnWidths;

            // Set row heights
            ws['!rows'] = [];
            ws['!rows'][0] = { hpt: 60 }; // Title row
            ws['!rows'][1] = { hpt: 20 }; // Empty row
            ws['!rows'][2] = { hpt: 30 }; // Header row

            // Set default row height for data rows
            for (let i = 3; i <= range.e.r; i++) {
                ws['!rows'][i] = { hpt: 25 };
            }

            // Merge cells for the title (first row, spans all columns)
            ws['!merges'] = [
                { s: {r: 0, c: 0}, e: {r: 0, c: 9} } // Merge first row across all columns (updated for 10 columns)
            ];

            // Add worksheet to workbook
            XLSX.utils.book_append_sheet(wb, ws, "Trainees");

            // Generate file name with current date
            const date = new Date();
            const fileDateString = date.getFullYear() + '-' +
                              String(date.getMonth() + 1).padStart(2, '0') + '-' +
                              String(date.getDate()).padStart(2, '0');
            const fileName = 'trainees-report-' + fileDateString + '.xlsx';

            // Generate the Excel file with special options for text formatting
            const wopts = {
                bookType: 'xlsx',
                bookSST: false,
                type: 'binary',
                cellStyles: true
            };

            // Generate workbooks
            XLSX.writeFile(wb, fileName, wopts);
        }
    });
        </script>
        <?php
    }
}
