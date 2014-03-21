mtgoxbalance
============

MtGox Balance

A safe way to enable charity initiatives (at your discretion) to verify how much you lost on MtGox

How does it work?

Step 1: Login to MtGox and copy your session cookie.

Step 2: Enter your session cookie, together with the email of your choice (does not have to be your MtGox email)

Step 3: When the above succeeded, log out from MtGox, and clear your session cookie.


That’s it!  You can verify if a balance is in our database by entering the email and mtgox username

Technical details and safety:

- Our software is open source.  We recommend other trusted community members to setup a similar site as ours, so we are not the only reference database.
- The email and username are hashed (SHA256) together, so noone can randomly guess email addresses or usernames.
