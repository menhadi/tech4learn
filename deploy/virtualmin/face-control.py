#!/usr/bin/python3 -I
"""Root-owned local controller. No arbitrary commands, paths, images or containers."""
import grp
import http.server
import json
import math
import os
import socketserver
import subprocess
import time
import threading

NAME = 'tech4learn-face'
IMAGE = 'exadel/compreface:1.2.0@sha256:0a36c8d2f089a0166c282a9ddbb61c2c845574c27e2586cc422a3a5cdf71c5ac'
SOCKET = '/run/tech4learn-face-control/control.sock'
GIB = 1024 ** 3
DEADLINE = None
CONTROL_LOCK = threading.Lock()

def docker(*args):
    return subprocess.run(['/usr/bin/docker', *args], capture_output=True, text=True,
                          check=True, timeout=max(1,min(25,DEADLINE-time.monotonic())) if DEADLINE else 25, env={'PATH':'/usr/bin:/bin','HOME':'/run/tech4learn-face-control','DOCKER_CONFIG':'/run/tech4learn-face-control/docker-config'}).stdout

def inspect():
    item = json.loads(docker('inspect', NAME))[0]
    host = item['HostConfig']
    bindings = host.get('PortBindings') or {}
    if (item['Config']['Image'] != IMAGE or host.get('Privileged') or
        host.get('NetworkMode') == 'host' or
        bindings != {'80/tcp':[{'HostIp':'127.0.0.1','HostPort':'8001'}]} or
        len(item.get('Mounts', [])) != 1 or
        item['Mounts'][0].get('Name') != 'tech4learn-face-data' or
        item['Mounts'][0].get('Destination') != '/var/lib/postgresql/data'):
        raise ValueError('Unexpected engine configuration. Operator review required.')
    return item

def host_capacity():
    with open('/proc/meminfo', encoding='ascii') as handle:
        mem = {line.split(':')[0]:int(line.split()[1])*1024 for line in handle}
    return {'cpus':os.cpu_count() or 1, 'memoryGiB':round(mem['MemTotal']/GIB,2),
            'availableGiB':round(mem['MemAvailable']/GIB,2), 'load':list(os.getloadavg())}

def limits(body, host, current):
    cpu, memory = body.get('cpus'), body.get('memoryGiB')
    if any(type(n) not in (int,float) or not math.isfinite(n) for n in (cpu,memory)):
        raise ValueError('Numeric limits required.')
    if not (0.5 <= cpu <= max(0.5, host['cpus']//2)) or cpu*2 != int(cpu*2):
        raise ValueError('CPU must use half-core steps and leave at least half the host available.')
    if not (2 <= memory <= math.floor(host['memoryGiB']/2)) or memory != int(memory):
        raise ValueError('RAM must use whole GiB and leave at least half the host available.')
    growth = max(0, memory-current['HostConfig']['Memory']/GIB)
    if growth > max(0,host['availableGiB']-2):
        raise ValueError('Insufficient available RAM; retain 2 GiB of current headroom.')
    return cpu, memory

def status(item=None):
    item = item or inspect()
    running = item['State']['Running']
    services = []
    if running:
        # supervisorctl returns nonzero while a child is starting; report unavailable safely.
        try:
            lines = docker('exec', item['Id'], 'supervisorctl', 'status').splitlines()
            services = [{'name':line.split()[0], 'state':line.split()[1]} for line in lines if line.startswith('compreface-')]
        except (subprocess.SubprocessError, IndexError):
            pass
    ready = len(services)==5 and all(s['state']=='RUNNING' for s in services)
    return {'running':running, 'ready':ready, 'state':item['State']['Status'],
            'oomKilled':item['State'].get('OOMKilled',False), 'services':services,
            'cpus':item['HostConfig']['NanoCpus']/1e9,
            'memoryGiB':item['HostConfig']['Memory']/GIB, 'host':host_capacity()}

def dispatch(body):
    global DEADLINE
    DEADLINE = time.monotonic()+55
    action = body.get('action')
    if action not in ('status','limits','start','stop','restart'):
        raise ValueError('Unsupported action.')
    if set(body) != ({'action','cpus','memoryGiB'} if action=='limits' else {'action'}):
        raise ValueError('Unexpected parameters.')
    item = inspect()
    if action == 'limits':
        cpu, memory = limits(body, host_capacity(), item)
        # Updating a stopped container avoids OOM-killing a running process on a RAM decrease.
        if item['State']['Running']:
            docker('stop','--time','20',item['Id'])
        docker('update','--cpus',str(cpu),'--memory',f'{int(memory)}g',
               '--memory-swap',f'{int(memory)}g', item['Id'])
        # Deliberately remains stopped; user starts after reviewing the new limits.
    elif action == 'start':
        if not item['State']['Running']:
            docker('start',item['Id'])
    elif action == 'stop':
        docker('stop','--time','20',item['Id'])
    elif action == 'restart':
        docker('restart','--time','20',item['Id'])
    return status()

class Handler(http.server.BaseHTTPRequestHandler):
    def log_message(self, *args):
        pass
    def do_POST(self):
        self.connection.settimeout(5)
        try:
            size = int(self.headers.get('Content-Length','0'))
            if self.path != '/' or not 0 < size <= 1024:
                raise ValueError('Invalid request.')
            body = json.loads(self.rfile.read(size))
            if not isinstance(body,dict):
                raise ValueError('Invalid request.')
            if not CONTROL_LOCK.acquire(blocking=False):
                raise ValueError('Controller is busy. Refresh status and retry.')
            try:
                value, code = dispatch(body), 200
            finally:
                CONTROL_LOCK.release()
        except ValueError as error:
            value, code = {'error':str(error)}, 400
        except Exception:
            value, code = {'error':'Face controller failed. Check engine status before retrying.'}, 503
        payload = json.dumps(value).encode()
        self.send_response(code)
        self.send_header('Content-Type','application/json')
        self.send_header('Content-Length',str(len(payload)))
        self.end_headers()
        self.wfile.write(payload)

if __name__ == '__main__':
    class Server(socketserver.ThreadingMixIn, socketserver.UnixStreamServer):
        daemon_threads = True
    if os.path.exists(SOCKET):
        os.unlink(SOCKET)
    with Server(SOCKET, Handler) as server:
        os.chown(SOCKET,0,grp.getgrnam('tech4learn').gr_gid)
        os.chmod(SOCKET,0o660)
        server.serve_forever()
