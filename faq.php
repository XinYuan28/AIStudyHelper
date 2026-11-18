<?php
// faq.php — Settings > FAQs (plain text, edit the answers right here)
require_once 'auth.php';
require_login();

$pageTitle  = 'FAQs';
$activePage = 'faq';
require 'settings_header.php';
?>
                <h2 class="panel-title">FAQs</h2>
                <p class="lead">Quick answers to common questions.</p>

                <div class="faq-list">
                    <details>
                        <summary>How do I delete my account?</summary>
                        <p>Log in, open the avatar menu at the top right and go to Security. In the "Delete account" box, enter your password and confirm. This permanently removes your profile, saved notes and quiz results, and it cannot be undone.</p>
                    </details>
                    <details>
                        <summary>Do you offer a premium or subscription plan for unlimited generations?</summary>
                        <p>No. AI Study Note Helper is free to use and there is no premium plan at the moment. If that ever changes, we will let you know first.</p>
                    </details>
                    <details>
                        <summary>How can I update my personal information or email address?</summary>
                        <p>Open the avatar menu and go to Profile to change your username, gender and birthday. Your email address and password are changed on the Security page, where we ask for your current password to make sure it is really you.</p>
                    </details>
                    <details>
                        <summary>What types of files can I upload to generate notes?</summary>
                        <p>You can upload PDF, Word (.docx), PowerPoint (.pptx), Excel (.xlsx), OpenDocument text (.odt), RTF, plain text (.txt), Markdown (.md) and CSV files, as well as audio files (.mp3, .m4a and .wav). You can add several files at once to make one set of notes, up to 100 MB in total.</p>
                    </details>
                    <details>
                        <summary>Can I edit the notes or flashcards after they are generated?</summary>
                        <p>Your notes are saved in your Study Hub so you can open them again any time. You can also export them as a PDF, PowerPoint slides, a web page or plain text and edit them in the app you like. At the moment we make quizzes rather than flashcards.</p>
                    </details>
                    <details>
                        <summary>How do I share my generated study materials with my classmates?</summary>
                        <p>Export your notes as a PDF, PowerPoint slides, a web page or plain text from the notes page, then send the file to your classmates however you like, for example by email or a chat app.</p>
                    </details>
                    <details>
                        <summary>Is my data private?</summary>
                        <p>Your notes and quiz results are only visible when you are logged in to your own account. You can read exactly what we collect and how we use it on the <a href="privacy.php">Privacy policy</a> page.</p>
                    </details>
                </div>

                <div class="card faq-more">
                    <h3>Still need help?</h3>
                    <p class="field-hint">Can't find what you're looking for? Send us a message and we'll get back to you.</p>
                    <div class="form-actions">
                        <a href="contact.php" class="btn">Contact us</a>
                    </div>
                </div>
<?php require 'settings_footer.php'; ?>