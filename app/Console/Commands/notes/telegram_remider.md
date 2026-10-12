# 🚀 Life Pilot AI - Backend API

ប្រព័ន្ធ Backend API ដែលត្រូវបានបង្កើតឡើងដោយប្រើប្រាស់ **Laravel Framework** ដោយមានបញ្ចូលមុខងារ **Reset Password ( Mailtrap )** និង **Telegram Bot Notification ( Event / Task Reminder )**។

---

## 🛠️ 1. ការដំឡើង និង Environment Setup (.env)

បង្កើត ឬ កែប្រែឯកសារ `.env` នៅក្នុង Root Project របស់អ្នក៖

```env
APP_NAME="Life Pilot AI"
APP_ENV=production
APP_KEY=base64:...
APP_DEBUG=false
APP_URL=[https://your-backend-url.onrender.com](https://your-backend-url.onrender.com)
FRONTEND_URL=[https://your-frontend-url.com](https://your-frontend-url.com)

# -----------------------------------------------------------------
# 📧 MAIL CONFIGURATION (Mailtrap Sandbox)
# -----------------------------------------------------------------
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=a878f43c59e84b
MAIL_PASSWORD=your_full_mailtrap_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="sanreaksmey01@gmail.com"
MAIL_FROM_NAME="${APP_NAME}"

# -----------------------------------------------------------------
# 🤖 TELEGRAM BOT CONFIGURATION
# -----------------------------------------------------------------
TELEGRAM_BOT_TOKEN=your_telegram_bot_token_here
TELEGRAM_CHAT_ID=your_telegram_chat_id_here