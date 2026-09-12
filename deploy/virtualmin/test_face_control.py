"""Runs without Docker; exercises the privileged command boundary with synthetic inspect data."""
import copy
import importlib.util
from pathlib import Path
import sys
import types
import unittest
from unittest.mock import patch
sys.dont_write_bytecode = True
if sys.platform == 'win32':
    sys.modules['grp'] = types.ModuleType('grp')
spec = importlib.util.spec_from_file_location('controller', Path(__file__).with_name('face-control.py'))
c = importlib.util.module_from_spec(spec)
spec.loader.exec_module(c)

class Controls(unittest.TestCase):
    def setUp(self):
        self.item={'Id':'fixed-container-id','Config':{'Image':c.IMAGE},'HostConfig':{'Memory':4*c.GIB,'NanoCpus':2e9,'PortBindings':{'80/tcp':[{'HostIp':'127.0.0.1','HostPort':'8001'}]}},'Mounts':[{'Name':'tech4learn-face-data','Destination':'/var/lib/postgresql/data'}],'State':{'Running':True,'Status':'running'}}
        self.host={'cpus':6,'memoryGiB':11.7,'availableGiB':6}
    def test_resource_guards(self):
        for cpu,mem in [(True,4),(float('nan'),4),(4,4),(2,6),(-1,4),(1.7,4),(2,1)]:
            with self.assertRaises(ValueError): c.limits({'cpus':cpu,'memoryGiB':mem},self.host,self.item)
        self.assertEqual(c.limits({'cpus':3,'memoryGiB':5},self.host,self.item),(3,5))
        with self.assertRaises(ValueError): c.limits({'cpus':3,'memoryGiB':5},{**self.host,'availableGiB':2},self.item)
    def test_scope_and_exposure_guards(self):
        import json
        for mutate in [lambda i:i['Config'].update(Image='other'),lambda i:i['HostConfig'].update(Privileged=True),lambda i:i['HostConfig'].update(PortBindings={}),lambda i:i['Mounts'].append({'Name':'other'})]:
            item=copy.deepcopy(self.item);mutate(item)
            with patch.object(c,'docker',return_value=json.dumps([item])):
                with self.assertRaises(ValueError):c.inspect()
    def test_only_fixed_container_is_updated_without_recreation(self):
        with patch.object(c,'inspect',return_value=self.item),patch.object(c,'host_capacity',return_value=self.host),patch.object(c,'status',return_value={}),patch.object(c,'docker') as docker:
            c.dispatch({'action':'limits','cpus':2,'memoryGiB':4})
            self.assertEqual(docker.call_args_list[0].args,('stop','--time','20','fixed-container-id'))
            self.assertEqual(docker.call_args_list[1].args,('update','--cpus','2','--memory','4g','--memory-swap','4g','fixed-container-id'))
            self.assertEqual(docker.call_count,2)
            with self.assertRaises(ValueError):c.dispatch({'action':'stop','container':'examelite'})
            with self.assertRaises(ValueError):c.dispatch({'action':'remove'})
    def test_start_is_idempotent(self):
        with patch.object(c,'inspect',return_value=self.item),patch.object(c,'status',return_value={}),patch.object(c,'docker') as docker:
            c.dispatch({'action':'start'})
            docker.assert_not_called()

if __name__=='__main__': unittest.main()
