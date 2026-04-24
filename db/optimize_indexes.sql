-- Performance Optimization Indexes
-- Run these SQL commands to speed up exam queries

-- Indexes for parts table (used in JOIN)
ALTER TABLE parts ADD INDEX idx_exam_id (exam_id);
ALTER TABLE parts ADD INDEX idx_part_number (part_number);

-- Indexes for passages table (used in JOIN)
ALTER TABLE passages ADD INDEX idx_part_id (part_id);
ALTER TABLE passages ADD INDEX idx_passage_order (passage_order);

-- Indexes for questions table (critical for JOIN performance)
ALTER TABLE questions ADD INDEX idx_part_id (part_id);
ALTER TABLE questions ADD INDEX idx_passage_id (passage_id);
ALTER TABLE questions ADD INDEX idx_order_index (order_index);
ALTER TABLE questions ADD INDEX idx_part_passage (part_id, passage_id); -- Composite index for the OR condition in JOIN

-- Indexes for options table
ALTER TABLE options ADD INDEX idx_question_id (question_id);
ALTER TABLE options ADD INDEX idx_option_label (option_label);

-- Check existing indexes
SHOW INDEXES FROM parts;
SHOW INDEXES FROM passages;
SHOW INDEXES FROM questions;
SHOW INDEXES FROM options;
