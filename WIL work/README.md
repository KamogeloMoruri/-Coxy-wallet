COXY WALLET AI MUSIC CONTEST – PROJECT DOCUMENTATION #1. Introduction

-The Coxy Wallet AI Music Contest is a web-based platform created to give people an opportunity to take part in a music competition using songs generated with artificial intelligence. The website allows users to enter the competition, submit their own AI-generated music, listen to songs submitted by other participants, and vote for the songs they like the most.

-The platform combines several technologies, including HTML, CSS, JavaScript, and Cardano blockchain technology. To enter the competition, a participant connects their Cardano wallet and pays an entry fee of 10 ADA. After the payment has been confirmed, the participant can submit their music and provide information about the song.

-The competition is based on community participation. Users can listen to the submitted tracks and vote for their favourite entries. When the competition period ends, the participant whose song has received the most votes is selected as the winner.

-The main idea behind the project is to create a simple and enjoyable platform where artificial intelligence, music, cryptocurrency, and community voting can be brought together.

#2. Aim of the Project

-The main aim of the Coxy Wallet AI Music Contest is to develop an online platform where users can create and submit AI-generated music and compete with other music creators.

-The project is designed to keep the competition process straightforward. Participants first connect their Cardano wallet and pay the required entry fee. They can then provide details about their song and upload their music. After the entry has been submitted, members of the community can listen to the track and vote for it.

In this way, the website gives both music creators and listeners an active role in the competition.

#3. Objectives of the Project

-The project was developed with several objectives in mind. These include:

-Creating a simple and visually appealing website for an AI music competition. -Allowing users to connect their Cardano cryptocurrency wallets. -Providing a way for participants to pay the 10 ADA competition entry fee. -Creating a submission form for users to enter their song information. -Allowing participants to upload their AI-generated music. -Giving users the ability to listen to submitted music. -Allowing members of the community to vote for their favourite songs. -Showing users how much time is left before the competition closes. -Making the website usable on different screen sizes and devices. -Providing users with a clear and straightforward experience when using the platform. #4. Technologies Used

-A number of different technologies were used to develop the Coxy Wallet AI Music Contest website. Each technology has a different role in the project.

4.1 HTML

-HTML, which stands for HyperText Markup Language, was used to build the main structure of the website.

-It was used to create the different parts of the page, including the navigation bar, headings, paragraphs, buttons, forms, sections, and footer.

-The HTML acts as the foundation of the website. It provides the content and structure that the other technologies build on.

4.2 CSS

-CSS, or Cascading Style Sheets, is used to control how the website looks.

-The HTML file is connected to a stylesheet called style.css. This stylesheet is responsible for the visual design of the website, including the colours, fonts, spacing, buttons, positioning, and general layout.

-The project also uses Google Fonts, specifically Fraunces and Inter. These fonts help give the website a clean, modern, and professional appearance.

4.3 JavaScript

-JavaScript is used to make the website interactive. The HTML file connects to a JavaScript file called app.js.

-JavaScript is responsible for handling many of the actions that users perform on the website. These include connecting a wallet, handling buttons, controlling the submission form, loading music entries, updating the countdown timer, and displaying messages to users.

-Without JavaScript, many of the interactive features of the website would not be able to function.

4.4 Cardano and Mesh SDK

-The project is designed to work with Cardano cryptocurrency wallets.

-The Mesh SDK is used within the JavaScript application to help the website communicate with supported Cardano wallets. This makes it possible for users to connect their wallets and interact with blockchain-based transactions.

-The use of Cardano also allows the competition entry fee to be handled using cryptocurrency rather than a traditional online payment method.

4.5 SVG

-The website also contains an SVG graphic. SVG stands for Scalable Vector Graphics.

-The SVG is used to create the colourful abstract shape displayed in the main section of the website. -Musical note symbols are also included around the graphic to make the design match the music theme.

-Using SVG allows the graphic to remain clear at different screen sizes without needing a separate image file.

#5. Website Structure

-The website is organised into several main sections so that users can easily understand and navigate the platform.

-The main parts of the website include the header, hero section, "How it works" section, music submission form, competition entries section, notification area, and footer.

-Each section has its own purpose. Together, these sections create the complete user experience for the competition.

-The structure also makes the website easier to maintain because different parts of the page can be updated independently.

#6. Header and Navigation

-The header is positioned at the top of the website and is one of the first things a user sees.

-It contains the Coxy Wallet name, navigation links, and a "Connect wallet" button.

-The navigation menu provides two main links: "How it works" and "Entries". These links allow users to move directly to the relevant parts of the webpage without having to scroll through the entire page.

-The "Connect wallet" button is used to start the wallet connection process. This is an important part of the website because participants need a Cardano wallet before they can enter the competition.

#7. Hero Section

-The hero section is the main introduction area of the website. It is designed to immediately tell visitors what the platform is about.

-The section contains the title "AI Music Contest" and the main heading:

-"Make something no one's heard before."

-This message encourages users to be creative and produce something original using AI music tools.

-The section also explains that participants can submit an AI-generated song for 10 ADA and that the community will decide which entry wins.

-There are two main buttons in this section. The first is the "Enter with 10 ADA" button, which is intended to start the competition entry process. The second is the "Listen & vote" button, which directs users towards the submitted entries.

-The hero section also contains an abstract colourful design with musical notes. This helps make the website more visually interesting and reinforces the music theme.

#8. Countdown Timer

-A countdown timer is included on the website to show users how much time remains before the current competition closes.

-The timer is divided into four parts:

Days Hours Minutes Seconds

-When the page initially loads, placeholder values are shown. JavaScript can then update these values as the competition deadline gets closer.

-The countdown is useful because it creates a sense of urgency and reminds participants that they have a limited amount of time to submit their entries.

-It also helps users know when the current competition round will come to an end.

#9. How the Competition Works

The "How it works" section explains the competition process in three simple stages.

Step 1: Connect and Pay

The first step is for the participant to connect their Cardano wallet.

Once the wallet is connected, the participant pays the 10 ADA entry fee. This payment is intended to secure their place in the competition.

Step 2: Submit Your Track

After the payment has been confirmed, the participant can submit their music.

They provide information such as the title of the song, the AI tool used to create it, a short description, their email address, and the actual audio file.

This gives the competition organisers and other users some background information about each submitted track.

Step 3: Community Votes

-Once the songs have been submitted, the community can listen to them and vote for their favourite entries.

The competition is designed so that each wallet receives one vote per track. When the competition ends, the entry with the highest number of votes is considered the winner.

-This approach allows the community to play an important role in deciding the outcome of the competition.

#10. Music Submission Form

-The website contains a submission form where participants can provide information about their music.

-The form asks for several details.

-The first field is the song title. This is a required field because every submission needs to have a name.

-The next field asks for the AI tool used to create the song. For example, a participant could enter the name of the AI music platform they used.

There is also an optional description field. Participants can use this field to explain their song or provide additional information about their creation.

The form also includes an email field. The email address can be used to send the participant confirmation messages or information about the competition results.

Finally, the participant must upload their audio file. The form is set up to accept MP3 and WAV files. The project specifies a maximum file size of 25 MB.

Once all the required information has been provided, the participant can click the "Submit entry" button.

#11. Entry Submission Process

The music submission form is placed inside a modal window. A modal is a pop-up section that appears on top of the main webpage.

The modal is initially hidden. It is intended to appear after the participant's 10 ADA payment has been successfully confirmed.

This creates a clear order for the entry process. Participants are expected to complete their payment before they can submit their music.

The overall process can therefore be summarised as:

Connect wallet → Pay 10 ADA → Payment confirmed → Submission form opens → Enter song details → Upload music → Submit entry

This makes the competition process easy for participants to follow.

#12. Music Entries Section

-The website includes a section called "This round's entries".

-This is where the songs submitted by participants are expected to be displayed.

-The section encourages users to listen to the available songs and then vote for the tracks they prefer.

-The entries are loaded dynamically rather than being permanently written into the HTML. When the page is first opened, users may see a "Loading entries..." message while the application retrieves the available submissions.

-Once the information has been loaded, JavaScript can display the music entries on the webpage.

-This approach makes it possible for new entries to be added without manually changing the main HTML page each time.

#13. Voting System

-Voting is an important part of the competition because it allows the community to help decide the winner.

-Users can listen to the submitted music and then vote for the tracks they like.

-According to the competition rules included in the website, each wallet is allowed one vote per track. This is intended to provide a fair voting process and reduce the possibility of users repeatedly voting for the same song.

-At the end of the competition period, the votes are counted and the entry with the most votes becomes the winner.

-The voting system therefore gives the community an active role rather than leaving the winner to be selected by the website owner alone.

#14. Notifications

-The website includes a notification feature known as a toast message.

-A toast is a small message that can appear on the screen to provide the user with information about an action.

For example, the website could use notifications to tell a user that their wallet has been connected successfully, that a payment has been completed, or that their music entry has been submitted.

-Notifications can also be used to explain problems. For example, if a wallet connection or payment fails, a message can be shown to explain what happened.

-This feature improves the user experience because it gives users immediate feedback when they interact with the website.

#15. Footer

-The footer is located at the bottom of the webpage.

-It contains the following message:

"Coxy Wallet AI Music Contest · a new round starts every month."

-The footer provides a simple ending to the website and reminds users that the competition is organised in monthly rounds.

#16. Accessibility

-Accessibility was also considered when creating the HTML structure.

-The website uses ARIA attributes such as aria-live in areas where information may change, such as the countdown timer and notification messages.

-These attributes can help users who use screen readers understand when important information on the page has changed.

-The modal's close button also includes an accessible label. This makes its purpose clearer to users who may not be able to identify the button visually.

-The form fields are also connected to labels, which makes the form easier to understand and use.

-These features help make the website more accessible to a wider range of users.

#17. Responsive Design

-The website is intended to work on different types of devices.

A viewport setting has been included in the HTML to help the webpage adjust to different screen sizes.

-This means the website can be designed to work on:

Desktop computers Laptops Tablets Mobile phones

The CSS stylesheet can then control how the different elements are arranged depending on the size of the screen.

Responsive design is important because users may not all access the competition using the same type of device.

#18. User Journey

-The user journey describes the steps a participant is expected to follow when using the website.

-First, the user visits the Coxy Wallet AI Music Contest website and reads the information about the competition.

-If they want to participate, they select the option to enter and connect their Cardano wallet.

-The participant then pays the 10 ADA entry fee. Once the payment has been confirmed, the music submission form becomes available.

The participant enters the details of their song, provides information about the AI tool they used, and uploads their music file.

After submitting the entry, the song can be displayed in the competition entries section.

Other users can then listen to the submitted tracks and vote for the songs they prefer.

When the competition closes, the votes are counted and the entry with the most votes is selected as the winner.

This creates a straightforward process from entering the competition through to selecting the winner.

#19. Testing

-Testing is an important part of the project because it helps ensure that the website works correctly and provides a good experience for users.

-The navigation links should first be tested to make sure that they take users to the correct sections of the page.

-The wallet connection should then be tested to ensure that supported Cardano wallets can connect successfully.

-The payment process should also be checked. The system should request the correct entry fee of 10 ADA and should respond appropriately if a transaction is successful or unsuccessful.

-The submission form should be tested to make sure that required fields cannot be left empty. The audio upload should also be checked to ensure that supported MP3 and WAV files can be uploaded.

-The maximum file size should be tested to ensure that files above the allowed 25 MB limit are handled correctly.

-The entries section should be tested to ensure that submitted songs are displayed correctly and can be played by users.

-The voting system should also be tested to make sure that users can vote according to the competition rules.

-Finally, the website should be tested on different devices and screen sizes to ensure that the layout remains clear and easy to use.

#20. Possible Improvements

-Although the current project provides the main structure and features for the competition, there are several ways in which it could be improved.

-One possible improvement would be to display the current prize pool. This would allow participants to see how much they could potentially win.

-An audio player could also be added to each competition entry so that users can easily play and pause songs.

-The website could display the number of votes received by each entry, allowing participants to see how their songs are performing.

-More detailed error messages could also be added. For example, users could be given clearer instructions if their wallet fails to connect, a transaction fails, or an uploaded file is too large.

-The website could also automatically check the size of uploaded audio files using JavaScript and reject files that are larger than the allowed limit.

-Another improvement would be to add a dedicated winner section after each competition round. This section could display the winning song and provide information about the winning participant.

-Further improvements could also be made to the mobile version of the website to ensure that the forms, buttons, navigation, and music players are easy to use on smaller screens.

#21. Conclusion

-The Coxy Wallet AI Music Contest is a web-based project that combines AI-generated music, blockchain technology, and community voting.

-The project uses HTML to create the structure of the website, CSS to control its visual design, and JavaScript to provide interactive features. Cardano and the Mesh SDK are used to support the wallet and blockchain-related parts of the application.

-The website provides participants with a clear process to follow. They can connect their Cardano wallet, pay the 10 ADA entry fee, submit their AI-generated music, and then allow other users to listen to and vote for their entry.

-The website also includes useful features such as a countdown timer, music submission form, navigation menu, notifications, and responsive design.
