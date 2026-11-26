# Written marking and custom question generation
Existing databases: import database_migration_quiz_marks.sql once. Applied on the development XAMPP database.
Custom quizzes request a new AI-generated question set matching the requested 5-30 count, regardless of the saved bank size.
The backend validates the exact count, normalized question uniqueness, distinct assessment keys and options. A second AI audit detects repeated assessment tasks. Flagged questions are replaced and the full set audited again, up to three repair rounds. An unavailable AI or unverified set returns a retry error instead of a short or knowingly duplicated quiz.
Written questions are graded against the server-side reference stored in the signed-in session, with integer marks out of 10. Equivalent wording can earn full marks. Correct but incomplete ideas earn partial marks. At least 5/10 excludes the question from weak topics and focused revision. Below 5/10 includes it.
Total marks and gamification points are separate. New results preserve marks_earned and marks_possible. Older result rows remain readable.
Generated runs expire after two hours; up to five are retained in the session. AI grading failures leave Check available for retry without assigning zero or advancing the quiz. The AI service is required for generation and semantic grading.
