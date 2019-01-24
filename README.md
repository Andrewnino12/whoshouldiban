# whoshouldiban
http://whoshouldiban.us-east-2.elasticbeanstalk.com/

"Who Should I Ban?" is a question every League player asks themselves before starting every ranked game. Historically, the school of thought has been to ban the current champion with the highest win rate that you don't intend on playing, but that's not quite correct. You need to factor in the likelihood the a champion will actually be picked in order to determine the "influence" that champion has over any extended set of games you play.

For example: a champion with a 50% ban rate, and 10% play rate will be banned 50 out of 100 games, and then picked in 10 out of the remaining 50 games. If they have a 100% win rate, they will win all 10 of those games, for a total influence rate of 10%.  Now imagine a champion with a 50% ban rate, a 60% win rate, and a 100% play rate. They will be banned 50 out of 100 games, picked every game from the remaining 50, and then win 30 of those 50 games. This gives them a higher influence rate (30%) than the previous example (10%), because although their win rate is lower, they're much more likely to be encountered.

Using a PHP web server hosted through AWS, I'm crawling through user's match histories, and storing info in a MySQL database to determine which champions you have the highest chance of losing based on win rates, pick rates, and ban rates. Then using the same web server, I've created a simple interface to display the information gathered for each tier and patch.

The League of Legends API Methods being used by my crawler are Match-V4, Summoner-V4, and League-V4.
