នេះជាសេចក្តីសង្ខេបស្ថាបត្យកម្ម និងដំណាក់កាលអនុវត្តទាំងស្រុង សម្រាប់ Email Notification Setup ក្នុង Reminder Module នៃប្រព័ន្ធ LifePilot AI៖

🏗️ ស្ថាបត្យកម្មរ៉ាប់រងការផ្ញើ Email (Architecture Overview)
[React Frontend] -> (API Request) -> [Laravel Backend / Database]
│
(Every Minute via Scheduler)
▼
[ProcessReminders Command]
│
(Check: remind_at <= NOW)
▼
[ReminderEmail Class & View]
│
▼
[User's Mail Inbox]
📋 ដំណាក់កាលអនុវត្តតាមជំហាន (Step-by-Step Summary)
១. កំណត់រចនាសម្ព័ន្ធ Mail (Environment & Mailer Setup)
កំណត់ SMTP Credentials ក្នុង .env ដើម្បីឱ្យ Laravel អាចផ្ញើ Email តាមរយៈ Mail Server (ឧ. Gmail, Mailtrap, ឬ SendGrid)៖

Code snippet
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="no-reply@lifepilot.ai"
MAIL_FROM_NAME="LifePilot AI"

# កំណត់ Timezone ឱ្យត្រូវនឹងតំបន់ (កម្ពុជា)

APP_TIMEZONE=Asia/Phnom_Penh
២. បង្កើត Mailable & Template (Email Layout & Design)
១. បង្កើត Mailable Class តាមរយៈ Artisan Command:

Bash
php artisan make:mail ReminderEmail
២. កំណត់ app/Mail/ReminderEmail.php ឱ្យទទួលទិន្នន័យ $reminder Object និងភ្ជាប់ទៅកាន់ Blade View Layout៖

Envelope: កំណត់ Dynamic Subject (🔔 ការរំលឹកពី LifePilot AI: {title})

Content: កំណត់ផ្លូវទៅកាន់ resources/views/emails/reminder.blade.php

៣. បង្កើត Console Command (Automated Processing Logic)
បង្កើត Artisan Command សម្រាប់ Filter ទាញយក Reminder រាល់ Record ណាដែលត្រូវតាមលក្ខខណ្ឌ៖

Bash
php artisan make:command ProcessReminders
លក្ខខណ្ឌត្រួតពិនិត្យក្នុង app/Console/Commands/ProcessReminders.php៖

channel === 'email'

is_triggered === false

remind_at <= Carbon::now()

ដំណើរការក្រោយផ្ញើ Email៖

បើជា once: ផ្លាស់ប្តូរ is_triggered = true

បើជា daily / weekly / monthly: គណនាថ្ងៃ/ម៉ោងបន្ទាប់ (addDay(), addWeek(), addMonth()) រួចរក្សាទុក is_triggered = false ដដែល

៤. កំណត់ Schedule Automation (Task Scheduler)
បន្ថែម Command ទៅក្នុង Laravel Scheduler ក្នុង routes/console.php (ឬ app/Console/Kernel.php) ឱ្យដំណើរការរៀងរាល់នាទី៖

PHP
use Illuminate\Support\Facades\Schedule;

Schedule::command('reminders:process')->everyMinute();
🧪 របៀបធ្វើតេស្តរត់ការផ្ញើ Email (Testing & Execution)
តេស្តរត់ Command ដោយផ្ទាល់:

Bash
php artisan reminders:process
តេស្តរត់ Scheduler ក្នុងអំឡុងពេលអភិវឌ្ឍន៍ (Local Dev):

Bash
php artisan schedule:work

========================================
Telegrambot
មូលហេតុដែលវា មិនដើរដោយស្វ័យប្រវត្តិ (លុះត្រាតែវាយ php artisan schedule:work ដោយដៃ) គឺដោយសារ VBScript មិនអាចស្គាល់ Command php ពេលវាដំណើរការជាលក្ខណៈ Background / Silent នៅក្នុង Windows។🛠️ ដំណោះស្រាយ៖ ប្រើ Windows Task Scheduler (វិធីដែលដើរ 100% លើ Windows)សូមលុប File .vbs នៅក្នុង Folder Startup ចោល រួចបង្កើត Task នៅក្នុង Windows Task Scheduler តាម ៦ ជំហានងាយៗខាងក្រោម៖ជំហានទី ១៖ បើក Task Schedulerចុច Key Win + R លើ Keyboardវាយបញ្ចូល taskschd.msc រួចចុច Enterជំហានទី ២៖ បង្កើត Task ថ្មីនៅ Panel ខាងស្តាំ ចុចលើ Create Task... (កុំជ្រើស Create Basic Task)នៅក្នុង Tab General:Name: វាយ LifePilot Schedulerគ្រីសជ្រើសរើស Run whether user is logged on or notគ្រីសជ្រើសរើស Run with highest privilegesជំហានទី ៣៖ កំណត់ Trigger (ឱ្យវាដំណើរការពេលបើកម៉ាស៊ីន)ចុចលើ Tab Triggers $\rightarrow$ ចុចប៊ូតុង New...ត្រង់ Begin the task: ជ្រើសយក At startup (ឬ At log on)ចុច OKជំហានទី ៤៖ កំណត់ Action (រត់ Command)ចុចលើ Tab Actions $\rightarrow$ ចុចប៊ូតុង New...ត្រង់ Action: ជ្រើសយក Start a programProgram/script: វាយបញ្ចូល cmd.exeAdd arguments (optional): បិទភ្ជាប់ (Paste) កូដខាងក្រោម៖Plaintext/c cd /d "D:\Software App\My Project\Web Application\An_AI_Assisted_Personal_Productivity_and_Management_System(LifePilot AI)\software\backend\life_pilot_ai_api" && php artisan schedule:work
Start in (optional): បិទភ្ជាប់ Path របស់ Project Backend របស់អ្នក៖PlaintextD:\Software App\My Project\Web Application\An_AI_Assisted_Personal_Productivity_and_Management_System(LifePilot AI)\software\backend\life_pilot_ai_api
ចុច OKជំហានទី ៥៖ រក្សាទុក (Save)ចុច OK នៅលើផ្ទាំង Create TaskWindows អាចនឹងទាមទារឱ្យបញ្ចូល Password គណនី Windows របស់អ្នក (បើមាន) រួចចុច OK ជាការស្រេច។🧪 វិធីធ្វើតេស្តរត់ភ្លាមៗនៅលើផ្ទាំង Task Scheduler ចុចលើ Task Scheduler Library នៅខាងឆ្វេងដៃស្វែងរក Task ឈ្មោះ LifePilot SchedulerRight-click លើវា រួចជ្រើសរើស Runពេលនេះ Task Scheduler នឹងរត់ php artisan schedule:work នៅ Background រហូតជាស្វ័យប្រវត្តិ ទោះបីជាអ្នកបិទ Terminal ឬ Restart ម៉ាស៊ីនក៏ដោយ! 🚀
