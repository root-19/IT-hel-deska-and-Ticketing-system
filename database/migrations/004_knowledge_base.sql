-- ============================================================
--  Knowledge base for the AI assistant (RAG)
--  The chat searches these articles and answers from them, in English or Tagalog.
--  Select your database first (phpMyAdmin), or:
--  mysql -u root helpdesk_db < database/migrations/004_knowledge_base.sql
--  Safe to run again: existing articles are kept.
-- ============================================================
CREATE TABLE IF NOT EXISTS kb_articles (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title       VARCHAR(150) NOT NULL,
  keywords    VARCHAR(500) NOT NULL DEFAULT '' COMMENT 'Comma-separated search terms, English and Tagalog',
  content     TEXT NOT NULL COMMENT 'Answer in English',
  content_tl  TEXT NULL COMMENT 'Answer in Tagalog/Taglish, optional',
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  created_by  INT UNSIGNED NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_kb_title (title),
  CONSTRAINT fk_kb_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO kb_articles (title, keywords, content, content_tl) VALUES
('No internet or campus Wi-Fi not connecting',
 'wifi, internet, network, no internet, slow internet, cannot connect, disconnect, signal, lan, ethernet, walang internet, mabagal, mahina signal, hindi maka connect, nawawala internet, offline',
 'Try these steps:
1. Turn Wi-Fi off and on, or restart your device.
2. Forget the campus network, then reconnect and log in again.
3. Check if others nearby are also offline. If everyone is, it is likely an outage, so report it once with the room number.
4. Move closer to the access point, away from thick walls.
5. For a wired lab PC, check that the LAN cable is plugged in firmly on both ends.
If it still does not work, submit a ticket with your room or lab number so a technician can check the access point or switch.',
 'Subukan ito:
1. I-off at i-on ang Wi-Fi, o i-restart ang device mo.
2. I-forget ang campus network, tapos mag-connect at mag-login ulit.
3. Tingnan kung offline din ang mga katabi mo. Kung lahat, malamang outage ito, kaya i-report minsan kasama ang room number.
4. Lumapit sa access point at umiwas sa makakapal na pader.
5. Kung naka-LAN ang lab PC, siguraduhing nakasaksak nang maayos ang cable sa magkabilang dulo.
Kung ayaw pa rin, mag-submit ng ticket kasama ang room o lab number para ma-check ng technician ang access point o switch.'),

('Forgot password for the IT Help Desk site',
 'forgot password, reset password, change password, cannot login, wrong password, locked, nakalimutan password, palitan password, hindi maka login, mali password, naka lock',
 'To reset your Help Desk password:
1. On the Log in page, click "Forgot password?".
2. Enter the email on your account and click Send reset code.
3. Open your email (check Spam too) and copy the 6-digit code.
4. Enter the code, then type your new password twice. It needs at least 8 characters with uppercase, lowercase, and a number.
5. Log in with your new password.
The code expires after 5 minutes. If you no longer have access to that email, visit the IT office with your school ID.',
 'Para ma-reset ang password mo sa Help Desk:
1. Sa Log in page, i-click ang "Forgot password?".
2. Ilagay ang email ng account mo at i-click ang Send reset code.
3. Buksan ang email mo (tingnan din ang Spam) at kopyahin ang 6-digit code.
4. Ilagay ang code, tapos i-type ang bagong password nang dalawang beses. Dapat 8 characters pataas, may uppercase, lowercase, at number.
5. Mag-login gamit ang bagong password.
Mag-e-expire ang code pagkalipas ng 5 minuto. Kung wala ka nang access sa email na iyon, pumunta sa IT office dala ang school ID mo.'),

('Login or reset code not arriving by email',
 'otp, code, verification code, login code, reset code, no email, email not received, spam, hindi dumating code, walang code, walang email, hindi natanggap',
 'If the code email has not arrived:
1. Wait a minute, then check your Spam, Junk, Promotions, and Updates folders.
2. Make sure you are checking the same email that is on your Help Desk account.
3. Use the "resend code" link. You can request a new one every 60 seconds.
4. Use only the newest code. Older codes stop working when a new one is sent.
If no code arrives after a few tries, submit a ticket from another account or visit the IT office so they can check your email address.',
 'Kung hindi dumarating ang code:
1. Maghintay ng isang minuto, tapos tingnan ang Spam, Junk, Promotions, at Updates folder.
2. Siguraduhing ang tinitingnan mo ay ang email na nakarehistro sa Help Desk account mo.
3. I-click ang "resend code". Puwede kang humingi ng bago kada 60 segundo.
4. Gamitin lang ang pinakabagong code. Hindi na gagana ang luma kapag may bagong pinadala.
Kung wala pa ring dumating pagkatapos ng ilang subok, pumunta sa IT office para ma-check ang email address mo.'),

('Student portal: grades, enrollment, or schedule will not load',
 'student portal, portal, grades, enrollment, schedule, assessment, balance, portal error, hindi makita grades, ayaw mag load portal, hindi ma open portal, enrollment problem',
 'If a portal page will not open or shows an error:
1. Log out, close the browser, and log in again.
2. Clear your browser cache or try a private/incognito window.
3. Try another browser (Chrome, Edge, Firefox) or another device.
4. If the portal is down for everyone, wait a while and try again later, especially during enrollment rush hours.
If only your account has the problem, or grades or records look wrong, submit a ticket under "Student Portal Problem" with a screenshot of the error.',
 'Kung ayaw bumukas ng portal page o may error:
1. Mag-logout, isara ang browser, tapos mag-login ulit.
2. I-clear ang browser cache o gumamit ng private/incognito window.
3. Subukan sa ibang browser (Chrome, Edge, Firefox) o ibang device.
4. Kung down ang portal para sa lahat, maghintay muna at subukan ulit mamaya, lalo na kapag rush ng enrollment.
Kung sa account mo lang ang problema, o mali ang grades o records, mag-submit ng ticket sa "Student Portal Problem" kasama ang screenshot ng error.'),

('School email account problems',
 'email, school email, gmail, outlook, google account, cannot open email, email password, email locked, hindi ma open email, ayaw mag login email, nawala email',
 'For school email problems:
1. Sign in with your full school email address, not just your username.
2. Check that Caps Lock is off and try a private/incognito window.
3. If you see "account disabled" or "suspended", only IT can restore it.
4. For missing messages, check Spam, Trash, and the All Mail folder.
If you still cannot get in, submit a ticket (Faculty & Staff: "Email / Account Request") or visit the IT office with your ID so they can verify you.',
 'Para sa problema sa school email:
1. Mag-sign in gamit ang buong school email address, hindi lang username.
2. Siguraduhing naka-off ang Caps Lock at subukan sa private/incognito window.
3. Kung "account disabled" o "suspended" ang lumalabas, IT lang ang makakapag-ayos nito.
4. Kung may nawawalang email, tingnan ang Spam, Trash, at All Mail folder.
Kung hindi ka pa rin makapasok, mag-submit ng ticket (Faculty & Staff: "Email / Account Request") o pumunta sa IT office dala ang ID mo para ma-verify ka.'),

('Printer not printing or paper jam',
 'printer, print, printing, paper jam, toner, ink, no printout, offline printer, hindi nagpi print, ayaw mag print, naipit papel, naipit ang papel, walang ink, walang toner',
 'For printer problems:
1. Check that the printer is on and shows no error light or message.
2. For a paper jam, open the trays and covers and pull the paper out slowly in the direction it feeds. Do not tear it.
3. Make sure the right printer is selected and that it is not paused or offline in Settings > Printers.
4. Cancel stuck jobs in the print queue, then restart the printer and the computer.
5. A faded or blank printout usually means low toner or ink.
If it still will not print, submit a ticket with the printer location and any error shown.',
 'Para sa problema sa printer:
1. Tingnan kung naka-on ang printer at walang error light o message.
2. Kung may naipit na papel, buksan ang tray at cover at dahan-dahang hilahin ang papel sa direksyon ng pagpasok nito. Huwag punitin.
3. Siguraduhing tama ang napiling printer at hindi ito naka-pause o offline sa Settings > Printers.
4. I-cancel ang mga naka-stuck na print job, tapos i-restart ang printer at computer.
5. Kapag malabo o blangko ang print, kadalasan ubos na ang toner o ink.
Kung ayaw pa rin mag-print, mag-submit ng ticket kasama ang lokasyon ng printer at ang error na lumalabas.'),

('Projector or TV shows no display',
 'projector, tv, display, no display, no signal, hdmi, vga, screen, presentation, walang display, walang lumalabas, no signal projector, ayaw lumabas sa projector',
 'For projector or TV display problems:
1. Check that the HDMI or VGA cable is firmly connected on both ends, and try the other cable or port if there is one.
2. Select the correct input source on the projector or TV remote (HDMI 1, HDMI 2, VGA).
3. On Windows, press Win + P and choose Duplicate.
4. On a Mac, open Settings > Displays and choose Mirror.
5. Turn the projector off, wait 30 seconds, and turn it on again.
If there is still no display, submit a ticket with the room number so a technician can check the equipment.',
 'Para sa projector o TV na walang display:
1. Siguraduhing nakakabit nang maayos ang HDMI o VGA cable sa magkabilang dulo, at subukan ang ibang cable o port kung meron.
2. Piliin ang tamang input source sa remote ng projector o TV (HDMI 1, HDMI 2, VGA).
3. Sa Windows, pindutin ang Win + P at piliin ang Duplicate.
4. Sa Mac, buksan ang Settings > Displays at piliin ang Mirror.
5. I-off ang projector, maghintay ng 30 segundo, tapos i-on ulit.
Kung wala pa ring display, mag-submit ng ticket kasama ang room number para ma-check ng technician.'),

('No sound from speakers or microphone not working',
 'sound, audio, speaker, speakers, mic, microphone, no sound, volume, headset, walang tunog, hindi marinig, ayaw gumana mic, mahina tunog',
 'For audio problems:
1. Check the volume on the computer and on the speaker itself, and make sure nothing is muted.
2. Click the speaker icon on the taskbar and choose the correct output device (speakers, HDMI, or headset).
3. For a microphone, open Settings > Sound > Input and pick the right mic. In Meet or Zoom, also choose it in the app settings.
4. Unplug and replug USB or audio cables, then restart the app.
If there is still no sound, submit a ticket with the room number and the device you are using.',
 'Para sa problema sa audio:
1. Tingnan ang volume sa computer at sa mismong speaker, at siguraduhing walang naka-mute.
2. I-click ang speaker icon sa taskbar at piliin ang tamang output device (speakers, HDMI, o headset).
3. Para sa mic, buksan ang Settings > Sound > Input at piliin ang tamang mic. Sa Meet o Zoom, piliin din ito sa settings ng app.
4. Tanggalin at isaksak ulit ang USB o audio cable, tapos i-restart ang app.
Kung wala pa ring tunog, mag-submit ng ticket kasama ang room number at ang device na gamit mo.'),

('Computer will not turn on, black screen, or blue screen',
 'computer, pc, unit, desktop, laptop, will not turn on, no power, black screen, blue screen, bsod, restart loop, ayaw bumukas, hindi nag o on, patay pc, sira pc, nag blue screen',
 'If a computer will not start:
1. Check that the power cable is plugged into the PC and the outlet or AVR, and that the AVR is switched on.
2. Check that the monitor is on and its cable is connected. A PC can be running with the monitor off.
3. Hold the power button for 10 seconds to force it off, wait, then turn it on again.
4. For a blue screen, write down or take a photo of the error code, then restart once.
Do not open the computer case. Submit a ticket (Computer Lab: "Broken / Defective Computer") with the room and PC number and what you see on screen.',
 'Kung ayaw bumukas ng computer:
1. Tingnan kung nakasaksak ang power cable sa PC at sa outlet o AVR, at naka-on ang AVR.
2. Tingnan kung naka-on ang monitor at nakakabit ang cable nito. Minsan bukas ang PC pero patay ang monitor.
3. Pindutin nang matagal ang power button ng 10 segundo para mamatay, maghintay, tapos i-on ulit.
4. Kung blue screen, isulat o kunan ng litrato ang error code, tapos i-restart minsan.
Huwag buksan ang case ng computer. Mag-submit ng ticket (Computer Lab: "Broken / Defective Computer") kasama ang room at PC number at kung ano ang nakikita sa screen.'),

('Computer is slow, freezing, or hanging',
 'slow, slow computer, freeze, freezing, hang, hanging, lag, not responding, stuck, mabagal, nag hang, nag lag, nagloloko, naka freeze, ayaw gumalaw',
 'If the computer is slow or freezing:
1. Save your work, then restart the computer. This fixes most slowdowns.
2. Close programs and browser tabs you are not using.
3. Press Ctrl + Shift + Esc to open Task Manager and end any app that shows "Not responding".
4. Do not turn off a computer while it says "Updating", because that can damage Windows.
5. Make sure there is free space on the drive and you are not running many apps at once.
If it keeps happening, submit a ticket with the PC number and what you were doing when it slowed down.',
 'Kung mabagal o nag-ha-hang ang computer:
1. I-save ang ginagawa mo, tapos i-restart ang computer. Kadalasan naaayos na ito.
2. Isara ang mga program at browser tab na hindi ginagamit.
3. Pindutin ang Ctrl + Shift + Esc para buksan ang Task Manager at i-end ang app na "Not responding".
4. Huwag patayin ang computer habang nag-a-"Updating", baka masira ang Windows.
5. Siguraduhing may free space pa ang drive at hindi sabay-sabay ang maraming app.
Kung paulit-ulit, mag-submit ng ticket kasama ang PC number at kung ano ang ginagawa mo nang bumagal ito.'),

('Request to install or update software',
 'install, software, application, app, program, license, update, ms office, activate, mag install, kailangan software, pa install, walang software',
 'School computers need IT approval before software is installed.
1. Check if the software is already installed by searching for it in the Start menu.
2. If it needs a license or is not installed, submit a ticket (Computer Lab: "Software Installation Request").
3. Include the software name and version, which room or PCs need it, and the date you need it by, such as the start of a class.
Please request early, because installing on a whole laboratory takes time.',
 'Kailangan ng approval ng IT bago mag-install ng software sa school computers.
1. Tingnan muna kung naka-install na ito sa pamamagitan ng pag-search sa Start menu.
2. Kung kailangan ng license o wala pa, mag-submit ng ticket (Computer Lab: "Software Installation Request").
3. Isama ang pangalan at version ng software, kung aling room o PC ang kailangan, at kung kailan ito kailangan, halimbawa bago magsimula ang klase.
Mag-request nang maaga dahil matagal mag-install sa buong laboratory.'),

('Online class problems: LMS, Google Meet, or Zoom',
 'lms, online class, google meet, meet, zoom, video call, camera, webcam, screen share, google classroom, hindi maka join, walang camera, ayaw gumana camera, online klase',
 'For online class problems:
1. Check your internet first. Open another website to confirm you are online.
2. Refresh the page or rejoin the meeting.
3. Allow the browser to use your camera and microphone (click the lock icon beside the address bar).
4. Close other apps that may be using the camera, such as another meeting app.
5. Update the browser or the Meet or Zoom app, or try a different browser.
If the LMS itself shows errors or you cannot access your class, submit a ticket (Faculty & Staff: "Online Class Problem") with a screenshot.',
 'Para sa problema sa online class:
1. I-check muna ang internet. Magbukas ng ibang website para masigurong online ka.
2. I-refresh ang page o mag-rejoin sa meeting.
3. Payagan ang browser na gamitin ang camera at mic (i-click ang lock icon sa tabi ng address bar).
4. Isara ang ibang app na gumagamit ng camera, gaya ng ibang meeting app.
5. I-update ang browser o ang Meet o Zoom app, o subukan sa ibang browser.
Kung ang LMS mismo ang may error o hindi mo ma-access ang klase, mag-submit ng ticket (Faculty & Staff: "Online Class Problem") kasama ang screenshot.'),

('Virus, pop-ups, or suspicious email (phishing)',
 'virus, malware, pop up, popup, ads, hacked, phishing, scam, suspicious email, suspicious link, fake email, na virus, na hack, kakaibang email, scam email, may virus',
 'If you suspect a virus or a phishing email:
1. Do not click links or open attachments in unexpected emails, even if they look like they come from the school.
2. Never enter your password on a page you opened from an email link. IT will never ask for your password or code.
3. If you already clicked or entered your password, change your password right away.
4. On a school computer showing strange pop-ups, stop using it, do not plug in USB drives, and tell the lab in-charge.
Submit a ticket describing what happened, including the sender address or a screenshot.',
 'Kung may hinala kang virus o phishing email:
1. Huwag i-click ang link o buksan ang attachment sa hindi inaasahang email, kahit mukhang galing sa school.
2. Huwag ilagay ang password mo sa page na binuksan mula sa link sa email. Hindi kailanman hihingin ng IT ang password o code mo.
3. Kung na-click mo na o nailagay mo na ang password, palitan agad ang password mo.
4. Kung may kakaibang pop-up sa school computer, itigil ang paggamit, huwag magsaksak ng USB, at sabihan ang lab in-charge.
Mag-submit ng ticket at ikuwento ang nangyari, kasama ang sender address o screenshot.'),

('Keyboard, mouse, or other peripheral not working',
 'keyboard, mouse, peripheral, usb, headset, monitor cable, missing mouse, sira keyboard, ayaw gumana mouse, walang mouse, hindi gumagana keyboard',
 'For a keyboard, mouse, or USB device that is not working:
1. Unplug it and plug it into a different USB port.
2. For wireless devices, check the batteries and the on/off switch, and reconnect the receiver.
3. Restart the computer.
4. Test the device on another computer to see if the device or the PC is the problem.
If it is broken or missing, submit a ticket (Computer Lab: "Broken / Defective Computer") with the room and PC number.',
 'Para sa keyboard, mouse, o USB device na ayaw gumana:
1. Tanggalin at isaksak sa ibang USB port.
2. Kung wireless, tingnan ang battery at on/off switch, at ikabit ulit ang receiver.
3. I-restart ang computer.
4. Subukan ang device sa ibang computer para malaman kung ang device o ang PC ang may problema.
Kung sira o nawawala, mag-submit ng ticket (Computer Lab: "Broken / Defective Computer") kasama ang room at PC number.'),

('How to submit and track a ticket',
 'ticket, submit ticket, create ticket, track ticket, ticket status, follow up, request, report problem, paano mag submit, paano mag ticket, mag report, status ng ticket',
 'To submit a ticket:
1. Log in, or register first if you are a student without an account.
2. Click "Submit a Ticket", choose the category that fits, and describe the problem. Include the room or PC number and any error message.
3. Track it under "My Tickets". The status goes Open, Assigned, In Progress, then Resolved.
4. When it is resolved you get an email and a notification. Open the ticket to confirm the fix and rate the support, or reopen it if the problem is still there.',
 'Para mag-submit ng ticket:
1. Mag-login, o mag-register muna kung student ka na wala pang account.
2. I-click ang "Submit a Ticket", piliin ang tamang category, at ilarawan ang problema. Isama ang room o PC number at ang error message.
3. Sundan ito sa "My Tickets". Ang status ay Open, Assigned, In Progress, tapos Resolved.
4. Kapag resolved na, makakatanggap ka ng email at notification. Buksan ang ticket para i-confirm ang fix at i-rate ang support, o i-reopen kung may problema pa rin.');
