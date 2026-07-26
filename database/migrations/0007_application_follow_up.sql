-- Add an explicit next-action date for timeline reminders and dashboard attention states.
ALTER TABLE job_applications
    ADD COLUMN follow_up_date DATE NULL AFTER application_date,
    ADD KEY job_applications_user_follow_up (user_id,follow_up_date);
