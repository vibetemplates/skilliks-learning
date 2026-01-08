-- Update ranking question text to include instruction about leaving uninterested items
-- Created: 2025-07-21

UPDATE survey_questions 
SET question_text = 'Put the topics that interest you in order of most interested to least interested. If you are not interested at all, leave the item in the left column.'
WHERE question_text = 'Put the topics that interest you in order of most interested to least interested'
AND question_type = 'ranking';