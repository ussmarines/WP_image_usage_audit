#!/usr/bin/env python3
"""Scan tracked files and Git history without printing matched private values."""
from __future__ import annotations
import argparse
from dataclasses import asdict, dataclass
from datetime import datetime, timezone
import hashlib, json, os, re, subprocess, sys, unicodedata
from pathlib import Path
MAX_SCAN_BYTES=20*1024*1024
ALLOWED_ENV_NAMES={'.env.example','.env.sample','.env.template','.env.dist'}
FORBIDDEN_BASENAMES={'.env','.pypirc','.netrc','auth.json','credentials.json','service-account.json','id_rsa','id_ed25519'}
FORBIDDEN_SUFFIXES={'.pem','.key','.p12','.pfx','.jks','.keystore','.tfstate'}
FORBIDDEN_IDENTITY_HASHES={'01e76a28977874f8b72265d0d39fa47c4105083556013f84ded1dad7798d01f7','ccb810ff1aea7ea61ea5c412bf549ca31b9d217d34357893d0ed97a54303b666','ec29e4a50ab3326b494e6126f3299ed436b1c24d3c508e364ee48345fc6c7a0b','a6710e26418bd4c6d2ee839605cd40c313ac3b79e599c1be31aa2bd711c665e3'}
PRIVATE_KEY_MARKERS=tuple(b'-----BEGIN '+v for v in (b'PRIVATE KEY-----',b'ENCRYPTED PRIVATE KEY-----',b'RSA PRIVATE KEY-----',b'OPENSSH PRIVATE KEY-----',b'EC PRIVATE KEY-----'))
SELF_PATH='.github/scripts/security_guard.py'; TOKEN_RE=re.compile(r'[a-z0-9]+'); ASCII_TOKEN_RE=re.compile(rb'[A-Za-z0-9]{3,}')
@dataclass(frozen=True)
class Finding: scope:str; location:str; category:str
def git(args,data=None): return subprocess.run(['git',*args],input=data,check=True,stdout=subprocess.PIPE,stderr=subprocess.PIPE).stdout
def tokens(text): return TOKEN_RE.findall(unicodedata.normalize('NFKD',text).encode('ascii','ignore').decode().lower())
def identity_match(items):
 c=list(items)+[''.join(items[i:i+2]) for i in range(max(0,len(items)-1))]+[''.join(items[i:i+3]) for i in range(max(0,len(items)-2))]
 return any(hashlib.sha256(x.encode()).hexdigest() in FORBIDDEN_IDENTITY_HASHES for x in c)
def path_categories(path):
 n=path.name.lower(); r=[]
 if n.startswith('.env') and n not in ALLOWED_ENV_NAMES:r.append('tracked environment file')
 if n in FORBIDDEN_BASENAMES:r.append('tracked credential file')
 if path.suffix.lower() in FORBIDDEN_SUFFIXES:r.append('tracked key or credential container')
 return r
def content_categories(data,check_keys=True):
 r=[]
 if check_keys and any(m in data for m in PRIVATE_KEY_MARKERS):r.append((None,'private-key material marker'))
 if b'\0' in data:
  if identity_match([x.decode('ascii','ignore').lower() for x in ASCII_TOKEN_RE.findall(data)]):r.append((None,'forbidden personal identifier in binary data'))
  return r
 for number,line in enumerate(data.decode('utf-8','replace').splitlines(),1):
  if identity_match(tokens(line)):r.append((number,'forbidden personal identifier'))
 return r
def scan_tree():
 f=[]
 for path in [Path(os.fsdecode(x)) for x in git(['ls-files','-z']).split(b'\0') if x]:
  f += [Finding('tracked-tree',str(path),c) for c in path_categories(path)]
  try:
   if path.stat().st_size>MAX_SCAN_BYTES:continue
   data=path.read_bytes()
  except OSError:f.append(Finding('tracked-tree',str(path),'unreadable tracked file'));continue
  for line,c in content_categories(data,path.as_posix()!=SELF_PATH):f.append(Finding('tracked-tree',f'{path}:{line}' if line else str(path),c))
 return f
def scan_metadata():
 output=git(['log','--all','--format=%H%x1f%an%x1f%ae%x1f%cn%x1f%ce%x1f%B%x1e']).decode('utf-8','replace');f=[];names=('author name','author email','committer name','committer email','message')
 for record in output.split('\x1e'):
  fields=record.strip('\n').split('\x1f',5)
  if len(fields)!=6:continue
  sha,*values=fields
  for field,value in zip(names,values):
   if identity_match(tokens(value)):f.append(Finding('git-history',f'commit:{sha[:12]}',f'forbidden personal identifier in {field}'))
 return f
def scan_blobs():
 objects={}
 for line in git(['rev-list','--objects','--all']).decode('utf-8','replace').splitlines():
  oid,_,path=line.partition(' ');objects.setdefault(oid,path)
 checks=git(['cat-file','--batch-check=%(objectname) %(objecttype) %(objectsize)'],('\n'.join(objects)+'\n').encode()).decode();eligible=[]
 for line in checks.splitlines():
  p=line.split()
  if len(p)==3 and p[1]=='blob' and int(p[2])<=MAX_SCAN_BYTES:eligible.append(p[0])
 process=subprocess.Popen(['git','cat-file','--batch'],stdin=subprocess.PIPE,stdout=subprocess.PIPE,stderr=subprocess.DEVNULL);assert process.stdin and process.stdout;f=[]
 for oid in eligible:
  process.stdin.write((oid+'\n').encode());process.stdin.flush();header=process.stdout.readline().decode('ascii','replace').split()
  if len(header)!=3:continue
  data=process.stdout.read(int(header[2]));process.stdout.read(1);path=objects.get(oid) or '<unknown-path>'
  for line,c in content_categories(data,path!=SELF_PATH):f.append(Finding('git-history',f'blob:{oid[:12]}:{path}'+(f':{line}' if line else ''),c.replace('forbidden personal identifier','forbidden personal identifier in historical content')))
 process.stdin.close();process.wait(timeout=30);return f
def main():
 p=argparse.ArgumentParser();p.add_argument('--history',action='store_true');p.add_argument('--report',type=Path);a=p.parse_args();f=scan_tree()
 if a.history:f+=scan_metadata()+scan_blobs()
 f=sorted(set(f),key=lambda x:(x.scope,x.location,x.category))
 if a.report:
  a.report.parent.mkdir(parents=True,exist_ok=True);a.report.write_text(json.dumps({'schema_version':1,'generated_at_utc':datetime.now(timezone.utc).isoformat(),'history_enabled':a.history,'safe_output':True,'matched_values_included':False,'status':'findings' if f else 'passed','finding_count':len(f),'findings':[asdict(x) for x in f]},indent=2,ensure_ascii=False)+'\n',encoding='utf-8')
 if f:
  for x in f:print(f'- {x.location}: {x.category} [{x.scope}]')
  print('No matched value was printed. Review the sanitized report and rotate any exposed secret.');return 1
 print('Security guard passed without exposing matched values.');return 0
if __name__=='__main__':sys.exit(main())
