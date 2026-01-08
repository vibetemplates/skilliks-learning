-- Fix unique constraint for ranking questions
-- Created: 2025-07-20
-- Issue: The unique constraint on (user_id, question_id) prevents multiple responses per question,
-- which is needed for ranking questions where each ranked item is a separate response.

-- Drop the existing unique constraint
ALTER TABLE survey_responses 
DROP INDEX unique_user_question;

-- Add a new unique constraint that includes answer_option_id
-- This allows multiple responses per question (for ranking) but prevents duplicate option selections
ALTER TABLE survey_responses 
ADD UNIQUE KEY unique_user_question_option (user_id, question_id, answer_option_id);

-- Note: This allows:
-- - Multiple options for ranking questions (different answer_option_id values)
-- - Multiple options for checkbox questions (different answer_option_id values)
-- - Single response for radio/dropdown questions (only one answer_option_id)
-- - Text responses (where answer_option_id is NULL)