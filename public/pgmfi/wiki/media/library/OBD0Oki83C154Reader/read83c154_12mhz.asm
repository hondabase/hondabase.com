;This is designed to read a 83C154


       org     0               ;start at the start


       ljmp    X4100           ;and jump up high                



        org    4100h           ;Jump up high...

X4100: setb    P1.7            ;we just flipped the ROM over below 4000h... 
                               ;since P1.7 is connected to the EA pin now...


       mov     a, #0ffh        ;set a = 256
idelay:        dec     a               ;decrement a
       inc     a               ;a+1 
       dec     a               ;a-1
       inc     a               ;=0
       dec     a               ;this
       inc     a               ;is
       dec     a               ;bs
       inc     a               ;to
       dec     a               ;waste
       inc     a               ;CPU
       dec     a               ;cycles
       inc     a               ;isn't
       dec     a               ;it
       inc     a               ;just
       dec     a               ;so
       inc     a               ;cute?
       
       dec     a               ;but it ultimately does decrement A by one
       jnz     idelay          ;until A zero we do nothing - delay to allow EA to work
                           
                           
init:  mov     dpl, #00h       ; DPL = 00h             >
       mov     dph, #00h       ; DPH = 00h             > DPTR = 0000h                                                                                                                        
                                               
       clr     TR1             ; setup serial routines for a 12.000Mhz crystal         
       mov     th1,#-13        ; TH1 = 253 for serial control
       mov     tl1,#-13        ; 
       anl     tmod, #0Fh      ; set timer 1 to mode 2 
       orl     TMOD, #20H      ;
       setb    tr1             ; turn on timer 1 - 4800 baud
       mov     scon, #40h      ; scon = 0100000 (mode 1)
       orl     pcon, #80h      ; smod (pcon.7) dbls baud rate to 4800
               
dump:  
       clr     A 
       movc    A, @A+DPTR      ;move internal ROM pointed at by the DPTR into A
               
       clr     TI              ;Clear transmit indicate
       mov     SBUF, A         ;Send the accumilator to the serial port
       jnb     TI,$            ;Pause until the TI bit is set - data has been sent          
       
       inc     DPTR            ;Increment DPTR to the next byte
       
       mov     A, dph          ;move DPH to A
       cjne    A, #40h, dump   ;if DPH = 40h then DPL =00h and DPTR 4000h
                                                       
       mov     a,0
       jnb     acc.0,$         ;and we loop till eternity
       end      
               
;X4100 eql     4100h)
