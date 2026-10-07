"""Pure per-user crontab candidate renderer; never installs or executes jobs."""
import re

BEGIN = '# BEGIN TECH4LEARN NATIVE SCHEDULER'
END = '# END TECH4LEARN NATIVE SCHEDULER'


def block(revision):
    if not re.fullmatch(r'[a-f0-9]{40}', revision):
        raise ValueError('An exact checked Git revision is required')
    artisan = f'/home/tech4learn/releases/{revision}/platform/artisan'
    shared = '/home/tech4learn/native-shared/storage'
    line = (f'* * * * * umask 077; /usr/bin/flock -n {shared}/framework/native-scheduler.lock '
            f'/usr/bin/php8.4 {artisan} schedule:run '
            f'>> {shared}/logs/scheduler.log 2>&1')
    return BEGIN + '\n' + line + '\n' + END + '\n'


def render(source, revision):
    candidate = block(revision)
    if '\x00' in source or '\r' in source:
        raise ValueError('Unexpected crontab encoding')
    if BEGIN in source or END in source:
        if source.count(BEGIN) != 1 or source.count(END) != 1:
            raise ValueError('Duplicate or incomplete managed scheduler block')
        pattern = re.escape(BEGIN) + r'\n(.*?)\n' + re.escape(END) + r'(?:\n|$)'
        found = re.search(pattern, source, re.S)
        if not found:
            raise ValueError('Malformed managed scheduler block')
        pin = re.search(r'/releases/([a-f0-9]{40})/platform/artisan', found.group(1))
        if not pin or found.group(0).rstrip('\n') != block(pin.group(1)).rstrip('\n'):
            raise ValueError('Changed managed scheduler requires review')
        source = source[:found.start()] + source[found.end():]
    # Do not silently add a second Tech4Learn scheduler from any old release/path.
    for line in source.splitlines():
        if line.lstrip().startswith('#'):
            continue
        if '/home/tech4learn/' in line and re.search(r'schedule:(run|work)', line):
            raise ValueError('Unmanaged Tech4Learn scheduler requires review')
    return source + ('' if not source or source.endswith('\n') else '\n') + candidate
