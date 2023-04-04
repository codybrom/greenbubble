Greenbubble
===========

Greenbubble is a simple, yet effective SMS-based bot that seamlessly integrates with Twilio, Airtable, and Slack. The bot receives incoming messages, processes them, and provides tailored responses based on specific trigger keywords stored in an Airtable base. Greenbubble also sends notifications to a designated Slack channel upon receiving a new message. Furthermore, it can handle multimedia messages (e.g., images) and store them in the Airtable base.

Features
--------

-   Receive SMS/MMS messages via Twilio
-   Process and autorespond to messages based on trigger keywords
-   Store incoming messages and attachments in Airtable
-   Send notifications via Slack when a new message is received

Requirements
------------

-   PHP 7.0 or higher
-   Twilio account with a phone number capable of receiving SMS
-   Airtable account with API key
-   Slack app with webhook URL

Installation and Setup
----------------------

1.  Clone the repository to your server:
`git clone https://github.com/codybrom/greenbubble/greenbubble.git`

2.  Set up a Twilio account and create a new phone number capable of receiving SMS. Configure a TWIML app pointed at the URL where your Greenbubble endpoint is located (e.g., `https://greenbubble.example.com/api/`) and set your Phone Number to use the TWIML app.

3.  Set up an Airtable base with the following tables and fields (see below)

4.  Set up a Slack webhook URL to recieve realtime notifications

5.  Configure environment variables on your server:

    -   `slackWebhookUrl`: Your Slack webhook URL
    -   `airtableApiKey`: Your Airtable API key
    -   `airtableBaseId`: Your Airtable base ID

Airtable Setup
--------------

To set up an Airtable account to work with this PHP code, you will need to follow these steps:

1.  Sign up for a free Airtable account at <https://airtable.com/signup>.
2.  Create a new base. This is where you will store your data.
3.  Create a table in your base named "Responses". This table will store the incoming SMS messages and their metadata.
4.  Add the following fields to your "Responses" table:
    -   "Sender" (text): This field will store the phone number of the person who sent the message.
    -   "Message" (long text): This field will store the text of the message.
    -   "Author" (link to another record): This field will link to the record of the sender in the "Senders" table (see step 6 below).
    -   "Attachments" (attachment): This field will store any images or other media attached to the message.
5.  Create a new table named "Commands". This table will store the trigger keywords and their responses. Add the following fields to this table:
    -   "Command" (text): This field will store the trigger keyword for each command.
    -   "Response" (long text): This field will store the response to send when the keyword is triggered.
6.  Create a new table named "Senders". This table will store the phone numbers of people who have sent messages. Add the following fields to this table:
    -   "Name" (text): This field will store the name of the sender.
    -   "Phone Number" (text): This field will store the phone number of the sender.
7.  Create an API key for your Airtable account. To do this, click on your profile picture in the top right corner of the screen and select "Account". Click on the "API" tab and then click "Generate API key".

Usage
-----

To use Greenbubble, simply send an SMS message containing a trigger keyword to your Twilio phone number. The bot will respond with the appropriate response as defined in the Airtable base. Messages sent to the Twilio number will be stored in the Airtable base and notifications will be sent to the configured Slack channel.


Integration with Vercel PHP Runtime
-----

Greenbubble can also be deployed on Vercel using the Vercel PHP Runtime. This runtime allows you to deploy serverless PHP applications on the Vercel platform. For more information on the Vercel PHP Runtime, please refer to [the official GitHub repository](https://github.com/vercel-community/php).

License
-------

Greenbubble is released under the [MIT License](https://opensource.org/licenses/MIT).
