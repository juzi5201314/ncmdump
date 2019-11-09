# ncmdump
netease cloud music copyright protection file dump (php)

# note
No cover and audio metadata, Because I didn't find a suitable audio file processing library in php.

# Usage
```main.php [*.ncm file/dir] <output path> --debug=0|1 -p num```  
```
--process=num pr -p num
```
Set the number of processes used. Default 12.

```
--debug=0(Disable) or --debug=1(Enable)
```
Print performance analysis. Default disable.
![image](https://github.com/juzi5201314/ncmdump/raw/master/2019-11-09%2011-27-10.png)
