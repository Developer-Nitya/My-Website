এই revised package-এ যা আছে:
1) Public website design/layout unchanged রাখা হয়েছে
2) Admin login + dashboard আছে
3) PPT / document / quiz upload database-backed করা আছে
4) Manual payment checkout আছে
5) Order + payment + delivery tracking database-এ save হয়
6) Contact messages database-এ save হয়
7) Comment submission endpoint + moderation structure আছে
8) Protected backup logs আছে (DB failure fallback)
9) Deep database review report update করা হয়েছে
10) upgrade_v2.sql যোগ করা হয়েছে

Hosting setup:
- config/database.php এ আপনার MySQL তথ্য দিন
- database/schema.sql import করুন
- setup-database.php run করে admin account তৈরি করুন
- setup শেষে setup-database.php delete করুন

Important notes:
- Public UI files ইচ্ছাকৃতভাবে অপরিবর্তিত রাখা হয়েছে
- SMTP থাকলে send-message.php আরও reliable হবে
- PHP PDO MySQL enabled থাকতে হবে
