import importlib.util
from pathlib import Path
import unittest

spec=importlib.util.spec_from_file_location('renderer',Path(__file__).with_name('render-native-cron.py'))
r=importlib.util.module_from_spec(spec);spec.loader.exec_module(r)

class CronTests(unittest.TestCase):
    def test_preserves_other_jobs_and_exact_retry(self):
        previous='# Existing jobs\n0 3 * * * /usr/bin/php /home/examelite/artisan schedule:run\n'
        rendered=r.render(previous,'a'*40)
        self.assertTrue(rendered.startswith(previous))
        self.assertIn('* * * * * umask 077; /usr/bin/flock',rendered)
        self.assertEqual(r.render(rendered,'a'*40),rendered)
        self.assertIn('/usr/bin/flock -n /home/tech4learn/native-shared/storage/framework/native-scheduler.lock',rendered)
        self.assertIn('/usr/bin/php8.4 /home/tech4learn/releases/'+'a'*40+'/platform/artisan',rendered)
        self.assertIn('>> /home/tech4learn/native-shared/storage/logs/scheduler.log 2>&1',rendered)

    def test_replaces_only_known_managed_release(self):
        old=r.render('MAILTO=\n','a'*40)
        new=r.render(old,'b'*40)
        self.assertNotIn('a'*40,new)
        self.assertEqual(new.count('schedule:run'),1)
        self.assertEqual(new.count(r.BEGIN),1)

    def test_refuses_unknown_or_duplicate_jobs(self):
        for source in [r.BEGIN, r.END, r.block('a'*40)*2,
                       r.block('a'*40).replace('php8.4','php8.1'),
                       '* * * * * php /home/tech4learn/old/artisan schedule:work\n',
                       '* * * * * php /home/tech4learn/old/artisan schedule:run\n',
                       'MAILTO=\r\n']:
            with self.assertRaises(ValueError):r.render(source,'b'*40)
        for pin in ['main','a'*8,'../other-site','a'*40+'; command']:
            with self.assertRaises(ValueError):r.render('',pin)

if __name__=='__main__':unittest.main()
