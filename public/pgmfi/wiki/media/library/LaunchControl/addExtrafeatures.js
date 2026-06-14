function addRpmLimitsToROM(rt) 
{
  if (rt == null) 
  	rt = window.external.rom.base;

  switch (rt) 
  {
    case 1: // P30
      addRpmLimitsToP30();
    break;
  }
}

function addShiftLightToROM(rt) 
{
  if (rt == null) 
  	rt = window.external.rom.base;

  switch (rt) 
  {
    case 1: // P30
      addShiftLightToP30();
    break;
  }
}

/********************************************************************************
*********************************************************************************
** add_RpmLimitsToP##
**
**Desc: These functions add Full Throttle Launch support.
**
********************************************************************************/

function addRpmLimitsToP30 () 
{
	window.external.rom.byteAt(0x3F98) = 0x03;
	window.external.rom.wordAt(0x3F99) = 0x79E6;
	_rom_fill (0x3F9B, 0x3FA9, 0xFF);


   	s = new Array(0xC5,0xA3,0xC0,0xC6,0xCD,0x34,0xEA,0x11,0x1C,0xC5,0xB4,0xC0,
   				  0x05,0xCD,0x09,0x67,0xEA,0x00,0x52,0x67,0xEA,0x00,0xCB,0x0D,
   				  0xC5,0xB9,0xC0,0xDA,0xCA,0x07,0x67,0xEA,0x00,0x52,0x67,0xEA,
   				  0x00,0xB5,0x1A,0xD0,0xA0,0x02,0xA2,0x08,0xD3,0x00,0x42,0xD3,
   				  0x02,0xA2,0x18,0xE5,0xF2,0xD5,0x1A,0x03,0xAA,0x3F,0xE5,0xAC,
   				  0x86,0x50,0x00,0x52,0xF9,0xE5,0xAC,0xCB,0xE0);

   	_rom_write(0x79E6, s, 0x45);
						  

// Disable AC from rom

   	_rom_fill(0x2A93, 0x2A95, 0x00);
	window.external.rom.byteAt(0x2c47) = 0x03;
	window.external.rom.byteAt(0x2c48) = 0x6C;
	window.external.rom.byteAt(0x2c49) = 0x2C;
   	_rom_fill(0x4306, 0x4308, 0x00);
	window.external.rom.byteAt(0x4431) = 0x03;
	window.external.rom.byteAt(0x4432) = 0x4e;
	window.external.rom.byteAt(0x4433) = 0x44;
   	_rom_fill(0x4838, 0x483a, 0x00);
   	_rom_fill(0x5D1E, 0x5D20, 0x00);

	window.external.refresh ();
	return;
}

////////////////////////////////////////////////////////////////////////////////
/*******************************************************************************
** add_ShiftLightToP##
**
**Desc: These functions add Full Throttle Launch support.
**
********************************************************************************/

function addShiftLightToP30 () 
{
	window.external.rom.byteAt(0x42AE) = 0x03;

	window.external.rom.wordAt(0x42AF) = 0x7A2B;
	s = new Array(0xCA,0x18,0x90,0x9D,0x4D,0x7A,0xC5,0x06,0x28,0xCD,0x09,0x90,
				0x9C,0x4B,0x7A,0xB5,0xAC,0xC1,0xCF,0x06,0xC5,0x22,0x0C,0x03,
				0xB1,0x42,0xC5,0x22,0x1C,0x03,0xB1,0x42,0xF0,0x00,0x01);
	_rom_write(0x7A2B, s, 0x23);

//----------------------------------------------------------------------
  	window.external.refresh ();
  	return;
}


////////////////////////////////////////////////////////////////////////////////
/******************************************************************************/

function _rom_write(startAt, v, count) 
{
	for (c = 0; c < count; c++) 
	{
	   window.external.rom.byteAt(startAt + c) = v[c];
	}
}

function _rom_fill (fromAdr, toAdr, byteFill) 
{
	if (byteFill == null) 
		byteFill = 0x00;

	for (i = fromAdr; i <= toAdr; i++) 
	{
		window.external.rom.byteAt(i) = byteFill;
	}
}
