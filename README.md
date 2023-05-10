# VOICEBOT

## Installation

 1. `cd your_app_dir && git clone https://github.com/gumione/voicebot.git . `
 2. `composer install` 
 3. `php bin/console doctrine:migrations:migrate`
 4.  `composer dump-env prod`
 5. Add your TG bot credentials into the `config/services.yaml` file (do not forget to allow /inline mode for bot so it can be mentioned using @botname)
 6. Put your DB connection settings into the `.env.local.php` file
 7. Put your audiofiles into the `public/audio` directory
 8. `php bin/console  app:import-audio` will scan the dir and add audio files to DB
 9. Enjoy!

## Live example

> http://t.me/gtalks_bot