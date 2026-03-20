total=283683000000 
med=196231000000 
oop=23147000000 
other=64305000000 
for n,v in [('Medicaid',med),('Out-of-pocket',oop),('Other payers',other)]: 
    print(n,round(v/total*100,1)) 
