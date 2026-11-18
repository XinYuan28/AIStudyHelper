<?php
// privacy.php — Settings > Privacy policy
// DRAFT: written from how the app currently works. Please review it before publishing.
require 'auth.php';
require_login();

$pageTitle  = 'Privacy policy';
$activePage = 'privacy';
require 'settings_header.php';
?>
                <h2 class="panel-title">Privacy policy</h2>
                <p class="lead">What we collect, why we collect it, and the choices you have.</p>

                <div class="card legal">
                    <p class="updated">Last updated: 17 November 2026</p>

                    <h3>1. About AI Study Note Helper</h3>
                    <p>AI Study Note Helper turns your study materials into notes, quizzes and audio. This policy explains how we handle your information when you use it.</p>

                    <h3>2. What we collect</h3>
                    <ul>
                        <li><b>Account details:</b> your email address, your username and your password. Your password is stored in a scrambled (hashed) form, so we cannot read it.</li>
                        <li><b>Profile details you choose to add:</b> your gender and birthday.</li>
                        <li><b>Your study content:</b> the files you upload, the notes generated from them, and your quiz questions and results.</li>
                        <li><b>Messages to us:</b> anything you send through the Contact us page, together with your account email so we can reply.</li>
                        <li><b>Technical data:</b> a session cookie that keeps you logged in, and your browser's local storage, which remembers your colour theme.</li>
                    </ul>

                    <h3>3. How we use it</h3>
                    <ul>
                        <li>To create your account and keep you logged in.</li>
                        <li>To generate notes, quizzes and audio from the materials you upload, and to save them for you.</li>
                        <li>To show your quiz progress and results.</li>
                        <li>To answer your messages and improve the app.</li>
                    </ul>

                    <h3>4. AI processing</h3>
                    <p>To make notes, quizzes and spoken audio, the text of your study materials and notes is sent to Google's Gemini AI service. Please do not upload anything you would not want processed this way, such as other people's private information.</p>

                    <h3>5. Sharing</h3>
                    <p>We do not sell your personal information. We only share it with the service providers needed to run the app (such as the AI service above), or when the law requires it.</p>

                    <h3>6. Keeping your information safe</h3>
                    <p>Your notes and results are only available when you are logged in to your own account, and passwords are never stored in plain text. No online service can promise perfect security, so please use a strong password and keep it to yourself.</p>

                    <h3>7. Your choices</h3>
                    <ul>
                        <li>Update your username, gender and birthday on the <a href="profile.php">Profile</a> page.</li>
                        <li>Change your email or password, or delete your account, on the <a href="security.php">Security</a> page. Deleting your account removes your profile, saved notes and quiz results. Messages you sent through Contact us may be kept so we can answer you.</li>
                        <li>Ask us a question about your information at any time through the <a href="contact.php">Contact us</a> page.</li>
                    </ul>

                    <h3>8. Changes to this policy</h3>
                    <p>If we change this policy, we will update the date at the top of this page.</p>

                    <h3>9. Contact</h3>
                    <p>Questions about privacy? Email us at <a href="mailto:<?= h(SUPPORT_EMAIL) ?>"><?= h(SUPPORT_EMAIL) ?></a> or use the <a href="contact.php">Contact us</a> page.</p>
                </div>
<?php require 'settings_footer.php'; ?>