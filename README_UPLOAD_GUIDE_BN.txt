EduPPT BD Website - Hosting Upload Guide
========================================

1) এই ফোল্ডারের সব ফাইল/ফোল্ডার একসাথে আপনার hosting public_html বা নির্ধারিত root directory-তে upload করুন।
2) Main file: index.html
3) Style file: style.css
4) Script file: script.js
5) Image assets: assets/images/

Logo setup system
-----------------
বর্তমানে header/footer-এ আগের text logo 그대로 রাখা হয়েছে, যাতে ওয়েবসাইটের structure বা look ভেঙে না যায়।
পরবর্তীতে নিজস্ব logo ব্যবহার করতে চাইলে:

Header logo:
- আপনার logo PNG ফাইলটি assets/images/header-logo.png নামে replace করুন।
- index.html ফাইলে header logo anchor class="logo" এর সাথে use-image class যোগ করুন:
  class="logo use-image"

Footer logo:
- আপনার footer logo PNG ফাইলটি assets/images/footer-logo.png নামে replace করুন।
- index.html ফাইলে footer logo div class="footer-logo" এর সাথে use-image class যোগ করুন:
  class="footer-logo use-image"

Social media links
------------------
Footer social icons-এর icon design পরিবর্তন করা হয়নি। Placeholder social platform links সেট করা আছে।
নিজস্ব profile/page link দিতে চাইলে index.html-এর Footer Section-এর href value replace করুন।

Image assets
------------
Testimonial avatar SVG ফাইলগুলো PNG ফরমেটে convert করে link করা হয়েছে। Product preview placeholder-এর জন্য PNG asset link করা হয়েছে।

Responsive check
----------------
Mobile, tablet এবং desktop width-এর জন্য non-destructive responsive CSS override যোগ করা হয়েছে। মূল structure অপরিবর্তিত রাখা হয়েছে।
