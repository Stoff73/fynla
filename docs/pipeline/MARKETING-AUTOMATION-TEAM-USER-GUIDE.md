# Marketing Automation: Team User Guide and Test Checklist

This guide is for marketing team members, reviewers, and managers. You do not
need technical knowledge or access to the website server.

Use this guide to:

- put each file in the correct shared location;
- test a new article safely on the development website;
- test the matching video after the article script is ready;
- recognise a successful result; and
- report a problem without creating duplicate work.

The automation is currently running on the Fynla development website. It checks
the Shared Drive approximately every five minutes. Automatic social publishing
is in test mode, so this testing must not publish a social post to the public.

## 1. The correct Shared Drive locations

Start in Google Drive and select **Shared drives**. Do not start in **My Drive**
or in an older folder that someone has shared with you.

| What you need | Exact location | What it is for |
|---|---|---|
| Article input | **Shared drives → Marketing Automation → Articles** | Team members put new Word documents here or create a Google Doc here. |
| Video input | **Shared drives → Marketing Automation → Videos** | Team members put finished source videos here after the matching script is ready. |
| Generated scripts | **Shared drives → Marketing Automation → Scripts** | The automation puts generated video scripts here. This is an output folder. |
| Progress tracker | **Shared drives → Marketing Automation → Fynla Marketing Pipeline Tracker** | The automation records the progress of each article and video here. |

The three folders—**Articles**, **Scripts**, and **Videos**—must remain directly
inside the **Marketing Automation** Shared Drive.

### Important rules

- Do not rename, move, replace, or duplicate any of the three folders.
- Do not upload anything to the Shared Drive's top level.
- Do not put articles or videos in **Scripts**. The automation owns that folder.
- Do not move the tracker into another folder or change its column headings.
- Do not delete or move older files merely to make a test look tidy.
- Do not use the older Marketing Automation folder in somebody's **My Drive**.
- Do not upload passwords, account-key files, customer data, or private
  financial records to these folders.

## 2. What files are accepted

### Articles

The **Articles** folder accepts either:

- a modern Microsoft Word document ending in `.docx`; or
- a document created directly with Google Docs.

It does not accept old `.doc` files, portable document files, plain text files,
images, spreadsheets, or Apple Pages files.

Prepare the article as follows:

1. Use **Heading 1** for the article title.
2. Use **Heading 2** and **Heading 3** for section headings.
3. Use normal paragraphs for the article text.
4. Bulleted lists, numbered lists, bold text, italic text, and links are
   supported.
5. Images inside the document are not imported. Add images later through the
   article-management page.
6. Comments, footnotes, text boxes, and tracked changes are ignored. Accept or
   reject tracked changes before uploading the final article.

Give every new article a unique, clear filename. A good testing pattern is:

```text
test-article-your-initials-YYYY-MM-DD.docx
```

For example:

```text
test-pension-guide-ab-2026-08-13.docx
```

Do not rename the file after the automation has imported it.

### Videos

The **Videos** folder accepts videos ending exactly in:

- `.mp4`; or
- `.mov`.

The filename before `.mp4` or `.mov` must be exactly the same as the matching
article's short web name. The article-management page shows this short web name
directly below the article title.

For example, if the short web name is:

```text
test-pension-guide-ab-2026-08-13
```

the video must be named exactly:

```text
test-pension-guide-ab-2026-08-13.mp4
```

These names will not match:

```text
test-pension-guide-ab-2026-08-13-final.mp4
Test-Pension-Guide-AB-2026-08-13.mp4
test-pension-guide-ab-2026-08-13.mov_
```

For the first video test, use a short landscape video:

- around 20 to 60 seconds long;
- widescreen, ideally 1920 by 1080 pixels;
- with the presenter kept near the centre of the picture; and
- without confidential or customer information.

## 3. Safe article upload test

This first test confirms that the automation can see and import an article. It
does not require a video.

1. Prepare a small test article using the article rules above. Include a clear
   test title, two short sections, one list, and a link.
2. Give it a new filename that has never been used before.
3. Open **Shared drives → Marketing Automation → Articles**.
4. Upload the `.docx` file, or create the Google Doc directly in this folder.
5. Wait at least five minutes. Allow up to ten minutes before treating a delay
   as a problem.
6. Sign in to the Fynla development website as an administrator.
7. Open the [development article-management page](https://csjones.co/fynla/admin/pipeline/articles).
8. Search for the test article's title or filename.
9. Confirm that the article appears as a **Draft**.
10. Select **Open** and check:
    - the title is correct;
    - the paragraphs and headings are present;
    - the list and link were retained;
    - the summary is sensible; and
    - **Open Word doc** leads back to the correct Drive file.
11. Copy the short web name displayed beneath the title. You will need this
    exact text if you continue to the video test.

### Article upload test passes when

- one draft article appears on the development article-management page;
- its title and main text are correct;
- its Drive link opens the uploaded source document; and
- no second copy of the article was created.

If you only need to test uploading, stop here. Do not publish the draft.

## 4. Generate and check the script

Only continue when the draft article has been reviewed and the test owner has
agreed that it may be published on the development website.

1. Open the test article on the development article-management page.
2. Correct the title, summary, and category if needed, then select **Save
   metadata**.
3. Select **Publish to local**. On the development website, this publishes the
   article only in the current development copy so the next automation check can
   generate its script.
4. Do not select **Push to live**. A test must never be pushed to the public
   Fynla website.
5. Wait for the next five-minute check. Script creation may take several more
   minutes, so allow up to fifteen minutes in total.
6. Open **Shared drives → Marketing Automation → Scripts**.
7. Find the newest generated Google Doc and confirm it belongs to the test
   article.
8. Open **Fynla Marketing Pipeline Tracker** at the top level of the Shared
   Drive.
9. Find the row for the article's short web name.
10. Confirm that the status is **Script Ready** and that the script link opens
    the same generated script.

The shared marketing inbox may also receive a message saying that the script is
ready.

### Script test passes when

- the article is visible on the development website;
- one matching script appears in **Scripts**;
- the tracker contains the correct article title and short web name; and
- the tracker status is **Script Ready**.

You can check the development article at:

```text
https://csjones.co/fynla/insights/SHORT-WEB-NAME
```

Replace `SHORT-WEB-NAME` with the exact text shown beneath the title in the
article-management page.

## 5. Safe video test

Do not upload the test video until the tracker says **Script Ready**. Uploading
it earlier will not speed up the process and may cause the video to be skipped.

1. Copy the article's short web name from the article-management page or the
   tracker. Do not type it from memory.
2. Rename the final source video so its name is exactly:

   ```text
   SHORT-WEB-NAME.mp4
   ```

   Use `.mov` instead only when the file really is a QuickTime video.
3. Confirm that the file does not end in `.mov_`, `.mp4_`, `.zip`, or any other
   extra characters.
4. Open **Shared drives → Marketing Automation → Videos**.
5. Upload the video once. Do not upload repeated copies with `(1)`, `final`, or
   `new` added to the filename.
6. Wait at least five minutes for detection. Video processing takes longer than
   article detection, so allow up to thirty minutes for a short first test.
7. Open **Fynla Marketing Pipeline Tracker**.
8. Find the latest row for the same short web name.
9. Confirm that the status is **Video Ready**.
10. Confirm that the video link opens the source video and that the result links
    in the notes can be opened by the reviewer.
11. Review the generated clips for:
    - the correct source video;
    - a sensible vertical crop;
    - the presenter remaining visible;
    - clear sound; and
    - no confidential information.

The shared marketing inbox may receive a message with clip-review links.

### Video test passes when

- the upload is detected only once;
- the tracker records **Video Ready** for the correct article;
- the generated clips open and play;
- the crop and sound are usable; and
- nothing has been published to a public social account.

## 6. How to read the tracker

The tracker is a progress record. The automation adds information to it. Team
members may add useful notes or assign an owner, but should not rename, remove,
or reorder its columns.

| Status | Plain-language meaning | What the team should do |
|---|---|---|
| **Script Ready** | The article was accepted and its video script was generated. | Review the script. Prepare the video using the exact short web name. |
| **Video In Progress** | The source video is being prepared. | Wait. Do not upload another copy. |
| **Video Ready** | The clips are ready to review. | Open the result links and check the clips. |
| **Published** | The approved content has completed the publishing stage. | Confirm it is the intended content and location. |
| **Rejected** | A reviewer rejected the content or result. | Read the notes before making a revised version. |

During development testing, **Script Ready** and **Video Ready** are the main
success points. Do not change a test row to **Published** merely to make the
test appear complete.

## 7. What to do when something goes wrong

### The article does not appear after ten minutes

1. Confirm it is in **Shared drives → Marketing Automation → Articles**.
2. Confirm it is a `.docx` file or a Google Doc.
3. Confirm the filename is new and has not been used by another article.
4. Refresh the development article-management page and search again.
5. Do not re-upload several copies. Record the filename and upload time, then
   ask the Fynla application administrator to check the automation.

### The article appears, but the formatting is wrong

1. Check that the title uses **Heading 1**.
2. Check that section titles use **Heading 2** or **Heading 3**.
3. Remember that embedded images, tracked changes, comments, and text boxes are
   not imported.
4. Correct the source document first. Use **Re-import from Drive** only when you
   understand that it replaces manual article-body edits.

### No script appears

1. Confirm the article is no longer a draft on the development website.
2. Wait up to fifteen minutes.
3. Search **Scripts** by the article title, not only by the original filename.
4. Check the tracker for the article's short web name.
5. If it is still missing, record the title, short web name, and time, then ask
   the application administrator to investigate. Do not publish the article
   again.

### The video is ignored

1. Confirm the tracker already showed **Script Ready** before upload.
2. Copy the short web name again and compare it character by character with the
   video filename.
3. Confirm the filename ends exactly in `.mp4` or `.mov`.
4. Confirm the video is in **Videos**, not in the Shared Drive's top level or in
   **Scripts**.
5. Do not rename or upload more copies while the administrator investigates.

### The video is still processing after thirty minutes

Record the article title, short web name, video filename, upload time, and the
last tracker status. Send those details to the application administrator. Do
not delete the source video and do not upload it again.

## 8. Test record and sign-off

Use this checklist for every controlled test:

- [ ] The tester used **Shared drives → Marketing Automation**.
- [ ] The article had a new `.docx` filename or was a new Google Doc.
- [ ] The article appeared once as a draft within ten minutes.
- [ ] The imported title, headings, paragraphs, list, and link were checked.
- [ ] The short web name was copied from the article-management page.
- [ ] The article was published only on the development website.
- [ ] A matching generated script appeared in **Scripts**.
- [ ] The tracker showed **Script Ready**.
- [ ] The video filename exactly matched the short web name.
- [ ] The video ended exactly in `.mp4` or `.mov`.
- [ ] The tracker showed **Video Ready**.
- [ ] The generated clips were reviewed.
- [ ] No public Fynla or social-media publishing took place.
- [ ] The tester recorded any problem before attempting another upload.

Record the tester's name, test date, article filename, short web name, video
filename, final tracker status, and pass or fail result in the team's normal
test record.

## 9. After the test

Do not delete test files or tracker rows without approval. The website may still
refer to them even after a Drive file is removed. Ask the application
administrator to remove or archive the test article and its generated results
as one controlled cleanup task.

If anything appears to be publishing publicly, stop testing immediately and
contact the application administrator. Do not try to repair folders, tracker
rows, or repeated uploads yourself.
